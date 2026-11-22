<?php

namespace App\Services\CupCard;

use App\Core\Models\Cart;
use App\Core\Models\Customer;
use App\Core\Models\CupCardEvent;
use App\Core\Models\Order;
use App\Core\Models\Product;
use App\Core\Models\Setting;
use App\Events\CupCardCompleted;
use App\Events\CupCardStamped;
use App\Services\AppOffer\AppOffer;
use App\Services\Rewards\RewardValueCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Cup card ("every cup gets cheaper").
 *
 * Each completed app order with a qualifying cup adds one stamp. The
 * discount for the next purchase is steps[stamps] and applies to ONE
 * unit, the most expensive qualifying one in the cart. The order that
 * buys the last slot completes the card (stamps back to 0). The card
 * resets when ttl_days pass after the last stamp (checked lazily).
 * A completed order that is later cancelled or fully refunded takes one
 * step back (restoring a completed card when nothing was stamped since).
 *
 * Settings (admin: Filament > App Rewards > Cup card):
 *   cup_card.enabled      bool, default false
 *   cup_card.steps        int[2..12] of 0-100, default [0,0,30,40,50,60,70,80,100]
 *   cup_card.category_ids int[]  (empty = any product)
 *   cup_card.min_price    EGP unit price a qualifying cup needs (default 0)
 *   cup_card.max_value    EGP cap of the card discount on the cup (0 = none)
 *   cup_card.ttl_days     default 24
 */
class CupCardService
{
    public const DEFAULT_STEPS = [0, 0, 30, 40, 50, 60, 70, 80, 100];

    public function __construct(private AppOffer $appOffer) {}

    public function enabled(): bool
    {
        return (bool) Setting::get('cup_card.enabled', false);
    }

    /** @return array<int, int> */
    public function steps(): array
    {
        $steps = Setting::get('cup_card.steps', self::DEFAULT_STEPS);
        if (is_string($steps)) {
            $steps = json_decode($steps, true);
        }
        if (! is_array($steps) || count($steps) < 2 || count($steps) > 12) {
            return self::DEFAULT_STEPS;
        }

        return array_values(array_map(fn ($s) => max(0, min(100, (int) $s)), $steps));
    }

    /** @return array<int, int> */
    public function categoryIds(): array
    {
        $ids = Setting::get('cup_card.category_ids', []);
        if (is_string($ids)) {
            $ids = json_decode($ids, true) ?? [];
        }

        return array_values(array_map('intval', (array) $ids));
    }

    public function minPrice(): float
    {
        return max(0.0, (float) Setting::get('cup_card.min_price', 0));
    }

    public function maxValue(): float
    {
        return max(0.0, (float) Setting::get('cup_card.max_value', 0));
    }

    public function ttlDays(): int
    {
        return max(1, (int) Setting::get('cup_card.ttl_days', 24));
    }

    public function percentFor(int $stamps): int
    {
        $steps = $this->steps();

        return $steps[max(0, min($stamps, count($steps) - 1))];
    }

    public function qualifies(?int $categoryId, float $unitPrice): bool
    {
        $categories = $this->categoryIds();
        if ($categories && ! in_array((int) $categoryId, $categories, true)) {
            return false;
        }

        return $unitPrice >= $this->minPrice() && $unitPrice > 0;
    }

    public function expiresAt(Customer $customer): ?CarbonImmutable
    {
        if ((int) $customer->cup_card_stamps <= 0 || ! $customer->cup_card_last_stamp_at) {
            return null;
        }

        return CarbonImmutable::instance($customer->cup_card_last_stamp_at)->addDays($this->ttlDays());
    }

    /** Stamps on the card right now (0 once the timer ran out). */
    public function effectiveStamps(Customer $customer): int
    {
        $expires = $this->expiresAt($customer);
        if ($expires === null || $expires->lessThanOrEqualTo(now())) {
            return 0;
        }

        return (int) $customer->cup_card_stamps;
    }

    /**
     * Cart pricing hook. Picks the one card cup and the card discount for it.
     *
     * @param float $base          cart subtotal minus the wallet gift
     * @param float $flatPercent   the flat app offer % (0 when the offer is off)
     * @return array{stamps:int,percent:int,applied:bool,item_id:?int,product_id:?int,unit_price:float,discount:float}
     */
    public function plan(Cart $cart, float $base, float $flatPercent): array
    {
        $plan = [
            'stamps' => 0, 'percent' => 0, 'applied' => false,
            'item_id' => null, 'product_id' => null, 'unit_price' => 0.0, 'discount' => 0.0,
        ];

        $customer = $cart->customer;
        if (! $this->enabled() || ! $customer || ! $this->appOffer->isAppPlatform($cart->platform)) {
            return $plan;
        }

        $plan['stamps'] = $this->effectiveStamps($customer);
        $plan['percent'] = $this->percentFor($plan['stamps']);
        if ($plan['percent'] <= 0 || $base <= 0) {
            return $plan;
        }

        // One unit of the line the wallet gift covers is not available to the card.
        $coveredId = null;
        if ($cart->walletItem && $cart->walletItem->isUsable()) {
            $coveredId = app(RewardValueCalculator::class)->coveredItemId($cart->walletItem, $cart);
        }

        $best = null;
        foreach ($cart->items as $item) {
            $product = $item->product;
            $units = (int) $item->quantity - ((int) $item->id === $coveredId ? 1 : 0);
            if (! $product || $units < 1) {
                continue;
            }
            $unit = (float) $item->price;
            if ($unit === 0.0) {
                $unit = (float) $product->base_price;
            }
            if (! $this->qualifies((int) $product->category_id, $unit)) {
                continue;
            }
            if ($best === null || $unit > $best['unit_price']) {
                $best = ['item_id' => (int) $item->id, 'product_id' => (int) $product->id, 'unit_price' => $unit];
            }
        }
        if ($best === null) {
            return $plan;
        }

        $discount = round($best['unit_price'] * $plan['percent'] / 100, 2);
        $cap = $this->maxValue();
        if ($cap > 0) {
            $discount = min($discount, $cap);
        }
        $discount = round(min($discount, $base), 2);

        $plan = array_merge($plan, $best);
        // No stacking on the cup: it gets the higher of the card and the flat %.
        $flatOnCup = round($best['unit_price'] * $flatPercent / 100, 2);
        if ($discount > $flatOnCup) {
            $plan['applied'] = true;
            $plan['discount'] = $discount;
        }

        return $plan;
    }

    /** Count one completed app order. Idempotent per order. */
    public function recordCompletedOrder(Order $order): void
    {
        if (! $this->enabled() || ! $order->customer_id || ! $this->orderQualifies($order)) {
            return;
        }

        $events = [];
        DB::transaction(function () use ($order, &$events) {
            $customer = Customer::query()->whereKey($order->customer_id)->lockForUpdate()->first();
            if (! $customer) {
                return;
            }
            $already = CupCardEvent::query()->where('order_id', $order->id)
                ->whereIn('type', [CupCardEvent::STAMP, CupCardEvent::COMPLETE])->exists();
            if ($already) {
                return;
            }

            $before = $this->expireIfStale($customer);
            $length = count($this->steps());
            $previousStampAt = $customer->cup_card_last_stamp_at?->toIso8601String();

            if ($before + 1 >= $length) {
                $after = 0;
                $type = CupCardEvent::COMPLETE;
                $events[] = new CupCardCompleted($customer, $order->id);
            } else {
                $after = $before + 1;
                $type = CupCardEvent::STAMP;
                $events[] = new CupCardStamped($customer, $after, (float) $this->percentFor($after), $order->id);
            }

            CupCardEvent::create([
                'customer_id'   => $customer->id,
                'order_id'      => $order->id,
                'type'          => $type,
                'stamps_before' => $before,
                'stamps_after'  => $after,
                'meta'          => ['prev_last_stamp_at' => $previousStampAt],
            ]);
            $customer->forceFill([
                'cup_card_stamps'        => $after,
                'cup_card_last_stamp_at' => now(),
            ])->save();
        });

        foreach ($events as $event) {
            event($event);
        }
    }

    /** A stamped order was cancelled / refunded after completion: step back. Idempotent per order. */
    public function reverseOrder(Order $order): void
    {
        if (! $order->customer_id) {
            return;
        }

        DB::transaction(function () use ($order) {
            $customer = Customer::query()->whereKey($order->customer_id)->lockForUpdate()->first();
            if (! $customer) {
                return;
            }
            $stamped = CupCardEvent::query()->where('order_id', $order->id)
                ->whereIn('type', [CupCardEvent::STAMP, CupCardEvent::COMPLETE])->first();
            if (! $stamped) {
                return; // never stamped (cancelled before completion, or disabled then)
            }
            $reversed = CupCardEvent::query()->where('order_id', $order->id)
                ->whereIn('type', [CupCardEvent::UNSTAMP, CupCardEvent::RESTORE])->exists();
            if ($reversed) {
                return;
            }

            $before = $this->expireIfStale($customer);
            $type = CupCardEvent::UNSTAMP;
            $after = max(0, $before - 1);
            $lastStampAt = $customer->cup_card_last_stamp_at;

            if ($stamped->type === CupCardEvent::COMPLETE) {
                $stampedSince = CupCardEvent::query()->where('customer_id', $customer->id)
                    ->where('id', '>', $stamped->id)
                    ->whereIn('type', [CupCardEvent::STAMP, CupCardEvent::COMPLETE])->exists();
                if (! $stampedSince && $before === 0) {
                    $type = CupCardEvent::RESTORE;
                    $after = max(0, count($this->steps()) - 1);
                    $prev = $stamped->meta['prev_last_stamp_at'] ?? null;
                    $lastStampAt = $prev ? CarbonImmutable::parse($prev) : $lastStampAt;
                }
            }

            CupCardEvent::create([
                'customer_id'   => $customer->id,
                'order_id'      => $order->id,
                'type'          => $type,
                'stamps_before' => $before,
                'stamps_after'  => $after,
            ]);
            $customer->forceFill([
                'cup_card_stamps'        => $after,
                'cup_card_last_stamp_at' => $after > 0 ? $lastStampAt : null,
            ])->save();
        });
    }

    /** Shape of GET /v1/cup-card. */
    public function status(Customer $customer): array
    {
        $enabled = $this->enabled();
        $steps = $this->steps();
        $stamps = $enabled ? $this->effectiveStamps($customer) : 0;
        $next = $this->percentFor($stamps);
        $expires = $stamps > 0 ? $this->expiresAt($customer) : null;

        return [
            'enabled'      => $enabled,
            'steps'        => $steps,
            'stamps'       => $stamps,
            'next_percent' => $next,
            'next_is_free' => $next >= 100,
            'card_length'  => count($steps),
            'expires_at'   => $expires?->setTimezone(config('core.business_timezone', 'Africa/Cairo'))->toIso8601String(),
            'days_left'    => $expires ? max(0, (int) ceil(now()->diffInSeconds($expires, false) / 86400)) : null,
            'category_ids' => $this->categoryIds(),
            'min_price'    => $this->minPrice(),
            'max_value'    => $this->maxValue(),
        ];
    }

    /** Reset a card whose timer ran out (call inside the customer lock). Returns the live stamps. */
    private function expireIfStale(Customer $customer): int
    {
        $stamps = (int) $customer->cup_card_stamps;
        if ($stamps > 0 && $this->effectiveStamps($customer) === 0) {
            CupCardEvent::create([
                'customer_id'   => $customer->id,
                'order_id'      => null,
                'type'          => CupCardEvent::EXPIRE,
                'stamps_before' => $stamps,
                'stamps_after'  => 0,
            ]);
            $customer->forceFill(['cup_card_stamps' => 0, 'cup_card_last_stamp_at' => null])->save();

            return 0;
        }

        return $stamps;
    }

    /** Does the order hold at least one qualifying cup line? */
    private function orderQualifies(Order $order): bool
    {
        $lines = collect($order->items_snapshot ?? [])
            ->filter(fn ($l) => ! empty($l['product_id']) && (int) ($l['quantity'] ?? 0) >= 1);
        if ($lines->isEmpty()) {
            return false;
        }

        $products = Product::query()->whereIn('id', $lines->pluck('product_id')->unique())->get()->keyBy('id');
        foreach ($lines as $line) {
            $product = $products->get($line['product_id']);
            if (! $product) {
                continue;
            }
            $unit = (float) ($line['price'] ?? 0);
            if ($unit === 0.0) {
                $unit = (float) $product->base_price;
            }
            if ($this->qualifies((int) $product->category_id, $unit)) {
                return true;
            }
        }

        return false;
    }
}
