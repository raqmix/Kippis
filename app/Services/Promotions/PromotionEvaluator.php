<?php

namespace App\Services\Promotions;

use App\Core\Models\Cart;
use App\Core\Models\Customer;
use App\Core\Models\Order;
use App\Core\Models\PromoCode;
use App\Core\Models\Store;
use App\Services\Promotions\Calculators\BuyXGetYCalculator;
use App\Services\Promotions\Calculators\FixedOffCalculator;
use App\Services\Promotions\Calculators\FreeDeliveryCalculator;
use App\Services\Promotions\Calculators\FreeItemCalculator;
use App\Services\Promotions\Calculators\PercentageOffCalculator;
use App\Services\Promotions\Calculators\PromotionCalculator;

/**
 * Central engine for the promotions system.
 *
 * `evaluate()` is the only public entry point. It:
 *   1. Loads every relevant candidate (the explicitly-applied promo +
 *      every auto-apply promo currently valid).
 *   2. Filters via the shared gate methods (store scope, segment,
 *      first-order-only, day/time window, min order).
 *   3. Routes each survivor through its type-specific calculator.
 *   4. Picks winners — non-stackable promos compete for the single
 *      "best" slot; stackable promos all stick.
 *
 * Mobile (and the rest of the app) never imports the calculators
 * directly — they only consume the EvaluationResult.
 */
class PromotionEvaluator
{
    public function evaluate(Cart $cart, ?Customer $customer, ?Store $store, ?string $explicitCode = null): EvaluationResult
    {
        $cart->loadMissing(['items.product', 'promoCode']);

        $subtotal = $this->computeSubtotal($cart);
        if ($subtotal <= 0) {
            return EvaluationResult::empty();
        }

        $candidates = $this->loadCandidates($cart, $explicitCode);
        if ($candidates->isEmpty()) {
            return EvaluationResult::empty();
        }

        $applied = [];
        foreach ($candidates as $promo) {
            // Eager-load M2M relations once so gating and the
            // calculators don't each fire their own SQL.
            $promo->loadMissing(['stores', 'categories', 'products']);

            if (!$this->passesAllGates($promo, $cart, $customer, $store, $subtotal)) {
                continue;
            }

            $scopeSubtotal = $this->subtotalInScope($promo, $cart, $subtotal);
            if ($scopeSubtotal <= 0 && $promo->discount_type !== PromoCode::TYPE_FREE_ITEM && $promo->discount_type !== PromoCode::TYPE_FREE_DELIVERY) {
                // Nothing in the cart matches this promo's category /
                // product scope and the type can't gift items, so skip.
                continue;
            }

            $calculator = $this->calculatorFor($promo->discount_type);
            if (!$calculator) {
                continue;
            }

            $result = $calculator->calculate($promo, $cart, $scopeSubtotal, $subtotal);
            if (!$result) {
                continue;
            }

            $applied[] = $result;
        }

        return $this->pickWinners($applied);
    }

    private function computeSubtotal(Cart $cart): float
    {
        return (float) $cart->items->sum(function ($item) {
            $price = (float) $item->price;
            if ($price === 0.0 && $item->product) {
                $price = (float) $item->product->base_price;
            }
            return $price * (int) $item->quantity;
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, PromoCode>
     */
    private function loadCandidates(Cart $cart, ?string $explicitCode)
    {
        $candidates = PromoCode::valid()->autoApply()->get();

        // The explicit code (entered via /cart/apply-promo) is honoured
        // even when it isn't an auto-apply promo. We prefer the cart's
        // currently stored promo_code_id over `$explicitCode` so a
        // recalculate triggered by an item-add doesn't accidentally
        // drop the previously-applied code.
        $explicitPromo = null;
        if ($cart->promo_code_id) {
            $explicitPromo = $cart->promoCode;
        } elseif ($explicitCode) {
            $explicitPromo = PromoCode::valid()->where('code', $explicitCode)->first();
        }

        if ($explicitPromo && !$candidates->contains('id', $explicitPromo->id)) {
            $candidates->push($explicitPromo);
        }

        return $candidates;
    }

    private function passesAllGates(PromoCode $promo, Cart $cart, ?Customer $customer, ?Store $store, float $subtotal): bool
    {
        if (!$promo->isValid()) {
            return false;
        }

        if ($subtotal < (float) $promo->minimum_order_amount) {
            return false;
        }

        if (!$this->passesStoreScope($promo, $store)) {
            return false;
        }

        if (!$this->passesUsageLimits($promo, $customer)) {
            return false;
        }

        if (!$this->passesSegmentCondition($promo, $customer)) {
            return false;
        }

        if (!$this->passesFirstOrderOnly($promo, $customer)) {
            return false;
        }

        if (!$this->passesDayOfWeek($promo)) {
            return false;
        }

        if (!$this->passesTimeWindow($promo)) {
            return false;
        }

        return true;
    }

    private function passesStoreScope(PromoCode $promo, ?Store $store): bool
    {
        if ($promo->stores->isEmpty()) {
            return true; // global promo
        }
        if (!$store) {
            return false; // promo is store-scoped but cart has no store
        }
        return $promo->stores->pluck('id')->contains($store->id);
    }

    private function passesUsageLimits(PromoCode $promo, ?Customer $customer): bool
    {
        if ($promo->usage_per_user_limit && $customer) {
            $used = $promo->usages()
                ->where('customer_id', $customer->id)
                ->count();
            if ($used >= (int) $promo->usage_per_user_limit) {
                return false;
            }
        }
        return true;
    }

    private function passesSegmentCondition(PromoCode $promo, ?Customer $customer): bool
    {
        $segments = $promo->conditionValue('segments');
        if (!is_array($segments) || empty($segments)) {
            return true; // everyone
        }
        if (in_array(PromoCode::SEGMENT_EVERYONE, $segments, true)) {
            return true;
        }

        if (!$customer) {
            return false; // anonymous can't match new_customer/loyal/staff
        }

        if (in_array(PromoCode::SEGMENT_STAFF, $segments, true) && (bool) $customer->is_staff) {
            return true;
        }

        if (in_array(PromoCode::SEGMENT_NEW_CUSTOMER, $segments, true) && $this->isFirstOrder($customer)) {
            return true;
        }

        if (in_array(PromoCode::SEGMENT_LOYAL, $segments, true) && $customer->orders()->count() >= 5) {
            return true;
        }

        return false;
    }

    private function passesFirstOrderOnly(PromoCode $promo, ?Customer $customer): bool
    {
        if (!$promo->conditionValue('first_order_only', false)) {
            return true;
        }
        if (!$customer) {
            return false;
        }
        return $this->isFirstOrder($customer);
    }

    private function isFirstOrder(?Customer $customer): bool
    {
        if (!$customer) {
            return false;
        }
        // "First order" = no completed/delivered order yet. We don't
        // count pending_payment or cancelled — the customer hasn't
        // actually transacted in either case.
        return !Order::where('customer_id', $customer->id)
            ->whereNotIn('status', ['cancelled', 'pending_payment'])
            ->exists();
    }

    private function passesDayOfWeek(PromoCode $promo): bool
    {
        $days = $promo->conditionValue('days_of_week');
        if (!is_array($days) || empty($days)) {
            return true;
        }
        // Carbon's dayOfWeekIso: Mon=1..Sun=7 — matches our convention.
        return in_array(now()->dayOfWeekIso, array_map('intval', $days), true);
    }

    private function passesTimeWindow(PromoCode $promo): bool
    {
        $window = $promo->conditionValue('time_window');
        if (!is_array($window) || empty($window)) {
            return true;
        }
        $from = $window['from'] ?? null;
        $to = $window['to'] ?? null;
        if (!$from || !$to) {
            return true;
        }
        $current = now()->format('H:i');
        return $current >= $from && $current <= $to;
    }

    private function subtotalInScope(PromoCode $promo, Cart $cart, float $cartSubtotal): float
    {
        $hasCategoryScope = $promo->categories->isNotEmpty();
        $hasProductScope = $promo->products->isNotEmpty();
        if (!$hasCategoryScope && !$hasProductScope) {
            return $cartSubtotal;
        }

        $categoryIds = $promo->categories->pluck('id')->all();
        $productIds = $promo->products->pluck('id')->all();

        return (float) $cart->items->reduce(function ($carry, $item) use ($categoryIds, $productIds) {
            $product = $item->product;
            if (!$product) {
                return $carry;
            }
            $matches = (in_array($product->id, $productIds, true)
                || in_array($product->category_id, $categoryIds, true));
            if (!$matches) {
                return $carry;
            }
            $price = (float) $item->price;
            if ($price === 0.0) {
                $price = (float) $product->base_price;
            }
            return $carry + $price * (int) $item->quantity;
        }, 0.0);
    }

    private function calculatorFor(string $type): ?PromotionCalculator
    {
        return match ($type) {
            PromoCode::TYPE_PERCENTAGE => app(PercentageOffCalculator::class),
            PromoCode::TYPE_FIXED => app(FixedOffCalculator::class),
            PromoCode::TYPE_BUY_X_GET_Y => app(BuyXGetYCalculator::class),
            PromoCode::TYPE_FREE_ITEM => app(FreeItemCalculator::class),
            PromoCode::TYPE_FREE_DELIVERY => app(FreeDeliveryCalculator::class),
            default => null,
        };
    }

    /**
     * @param  array<int, AppliedPromotion>  $applied
     */
    private function pickWinners(array $applied): EvaluationResult
    {
        if (empty($applied)) {
            return EvaluationResult::empty();
        }

        // Priority desc, then amount desc — strongest match wins ties.
        usort($applied, function (AppliedPromotion $a, AppliedPromotion $b) {
            return $b->priority <=> $a->priority
                ?: $b->amountOff <=> $a->amountOff;
        });

        $winners = [];
        $haveNonStackable = false;
        foreach ($applied as $candidate) {
            if ($candidate->stackable) {
                $winners[] = $candidate;
                continue;
            }
            // First non-stackable in priority order claims the slot;
            // subsequent non-stackables are skipped. Stackables can
            // still pile on alongside it.
            if (!$haveNonStackable) {
                $winners[] = $candidate;
                $haveNonStackable = true;
            }
        }

        $total = array_sum(array_map(fn (AppliedPromotion $p) => $p->amountOff, $winners));
        return new EvaluationResult($winners, round($total, 2));
    }
}
