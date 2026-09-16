<?php

namespace App\Services;

use App\Core\Models\Customer;
use App\Core\Models\CustomerSpendReward;
use App\Core\Models\Order;
use App\Core\Models\Product;
use App\Core\Models\SpendRewardTier;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Spend-milestone rewards. Ops configures tiers in Filament; this
 * service is the engine that:
 *   1. Increments customers.lifetime_spent_piasters on order completion
 *      (called from OrderObserver::updated).
 *   2. Reverses on full refund (called from RefundService).
 *   3. Checks active tiers after each spend change and issues vouchers
 *      for any newly-crossed thresholds. Idempotent via the unique
 *      (customer_id, tier_id, cycle_index) index — losing racers just
 *      catch the constraint violation and move on.
 *   4. Applies picked vouchers to a customer's cart by injecting
 *      zero-price line items (cart total calc treats them as free).
 *
 * Tier config is generic — a tier is any (threshold, cycle, choice
 * groups) combination the client wants. The "spend 2000 EGP → salad or
 * sandwich + drink" ask is just the first seeded row; ops can add more
 * tiers without any code change.
 */
class SpendRewardService
{
    /**
     * Record a completed order's spend on the customer's lifetime
     * counter and issue any vouchers crossed. Called from
     * OrderObserver::updated on the `→completed` transition.
     */
    public function recordSpend(Order $order): void
    {
        if (! $order->customer_id) {
            return;
        }
        $delta = $this->orderSpendPiasters($order);
        if ($delta <= 0) {
            return;
        }

        DB::transaction(function () use ($order, $delta) {
            $customer = Customer::whereKey($order->customer_id)
                ->lockForUpdate()
                ->first();
            if (! $customer) {
                return;
            }
            $customer->increment('lifetime_spent_piasters', $delta);
            $customer->refresh();
            $this->checkAndIssue($customer);
        });
    }

    /**
     * Reverse a full refund's spend from the customer's lifetime
     * counter. Called from RefundService when a full refund completes.
     *
     * Does NOT revoke already-issued vouchers by default — that's a
     * per-tier policy decision the client can define later if needed.
     * We just clamp the counter at 0 so we don't underflow.
     */
    public function reverseSpend(Order $order): void
    {
        if (! $order->customer_id) {
            return;
        }
        $delta = $this->orderSpendPiasters($order);
        if ($delta <= 0) {
            return;
        }
        DB::transaction(function () use ($order, $delta) {
            $customer = Customer::whereKey($order->customer_id)
                ->lockForUpdate()
                ->first();
            if (! $customer) {
                return;
            }
            $newTotal = max(0, (int) $customer->lifetime_spent_piasters - $delta);
            $customer->update(['lifetime_spent_piasters' => $newTotal]);
        });
    }

    /**
     * For each active tier, work out the highest `cycle_index` the
     * customer has now earned and insert any missing rows. Idempotent
     * per crossing via the unique index.
     */
    public function checkAndIssue(Customer $customer): array
    {
        $issued = [];
        $lifetime = (int) $customer->lifetime_spent_piasters;

        foreach (SpendRewardTier::active()->get() as $tier) {
            $threshold = (int) $tier->threshold_piasters;
            if ($threshold <= 0 || $lifetime < $threshold) {
                continue;
            }

            $maxCycle = $tier->cycle === 'repeating'
                ? intdiv($lifetime, $threshold) - 1
                : 0;

            for ($idx = 0; $idx <= $maxCycle; $idx++) {
                try {
                    $reward = CustomerSpendReward::create([
                        'customer_id' => $customer->id,
                        'spend_reward_tier_id' => $tier->id,
                        'threshold_snapshot_piasters' => $threshold,
                        'cycle_index' => $idx,
                        'status' => CustomerSpendReward::STATUS_AVAILABLE,
                        'issued_at' => now(),
                        'expires_at' => $tier->voucher_ttl_days
                            ? now()->addDays($tier->voucher_ttl_days)
                            : null,
                    ]);
                    $issued[] = $reward;
                    Log::info('spend_reward.issued', [
                        'customer_id' => $customer->id,
                        'tier_id' => $tier->id,
                        'cycle_index' => $idx,
                        'threshold_piasters' => $threshold,
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    // Unique-index violation → already issued for this
                    // customer + tier + cycle. Expected on repeat
                    // checks; swallow silently.
                    if (str_contains($e->getMessage(), 'Duplicate') || (int) $e->getCode() === 23000) {
                        continue;
                    }
                    throw $e;
                }
            }
        }

        return $issued;
    }

    /**
     * Persist the customer's picks (product per choice group). Moves
     * the voucher from `available` to `picked`. Re-picking replaces
     * the previous picks_json.
     */
    public function setPicks(CustomerSpendReward $reward, array $picks): CustomerSpendReward
    {
        if (! in_array($reward->status, [
            CustomerSpendReward::STATUS_AVAILABLE,
            CustomerSpendReward::STATUS_PICKED,
        ], true)) {
            throw new \DomainException('This reward can\'t be re-picked at its current status.');
        }

        $tier = $reward->tier;
        if (! $tier) {
            throw new \DomainException('Reward tier missing.');
        }

        $this->validatePicks($tier, $picks);

        $reward->update([
            'status' => CustomerSpendReward::STATUS_PICKED,
            'picks_json' => $picks,
            'picked_at' => now(),
        ]);
        return $reward->fresh();
    }

    // NOTE — v1 does not auto-inject the picked products into the
    // customer's cart or the resulting order. The customer picks their
    // items and gets a "show this at pickup" screen; branch staff hand
    // over the free items and don't need to change the order total.
    //
    // v2 wiring plan (documented for the next iteration):
    //   1. Add `carts.spend_reward_id` FK, `orders.spend_reward_id` FK.
    //   2. New endpoint `POST /v1/spend-rewards/{id}/apply-to-cart`
    //      links the voucher to the cart and moves it to `applied`.
    //   3. In the checkout controller, when finalizing an order off a
    //      cart with `spend_reward_id`, append the picked products
    //      into orders.items_snapshot at unit_price=0 with a
    //      `source: 'spend_reward'` marker.
    //   4. OrderObserver already handles the `applied → redeemed`
    //      transition off the marker (see the ->completed branch).

    /**
     * Mark a voucher as redeemed against a completed order. Called from
     * OrderObserver when an order tied to a cart with an applied
     * voucher transitions to a finalized status.
     */
    public function markRedeemed(CustomerSpendReward $reward, Order $order): CustomerSpendReward
    {
        $reward->update([
            'status' => CustomerSpendReward::STATUS_REDEEMED,
            'order_id' => $order->id,
            'redeemed_at' => now(),
        ]);
        return $reward->fresh();
    }

    /**
     * How much of an order counts toward the milestone. Uses the
     * order's `total` (post-discounts, post-promos) in piasters —
     * matches the semantics of loyalty points, so ops can reason about
     * both counters together.
     */
    private function orderSpendPiasters(Order $order): int
    {
        return Money::toPiasters((float) $order->total);
    }

    /**
     * Reject picks that don't match the tier's shape: one product per
     * choice group, each product must belong to at least one of the
     * group's eligible categories.
     */
    private function validatePicks(SpendRewardTier $tier, array $picks): void
    {
        $groups = $tier->choice_groups ?? [];
        if (count($picks) !== count($groups)) {
            throw new \DomainException(
                sprintf('Expected %d pick(s), got %d.', count($groups), count($picks)),
            );
        }

        foreach ($groups as $i => $group) {
            $pick = $picks[$i] ?? null;
            if (! is_array($pick) || ! isset($pick['product_id'])) {
                throw new \DomainException("Missing product_id for group #{$i}.");
            }
            $productId = (int) $pick['product_id'];
            $categoryIds = array_map('intval', $group['category_ids'] ?? []);
            if (empty($categoryIds)) {
                continue; // Group with no restriction — any product accepted.
            }
            $product = Product::find($productId);
            if (! $product) {
                throw new \DomainException("Product #{$productId} not found.");
            }
            $productCategoryId = (int) ($product->category_id ?? 0);
            if (! in_array($productCategoryId, $categoryIds, true)) {
                throw new \DomainException(
                    "Product #{$productId} isn't eligible for group #{$i}.",
                );
            }
        }
    }
}
