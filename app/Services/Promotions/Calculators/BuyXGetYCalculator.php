<?php

namespace App\Services\Promotions\Calculators;

use App\Core\Models\Cart;
use App\Core\Models\PromoCode;
use App\Services\Promotions\AppliedPromotion;

/**
 * "Buy X get Y free" — the classic Factory promo (every 10 meals = 3
 * free). Config: {buy: 10, get: 3}.
 *
 * Algorithm: explode every in-scope cart line into unit-priced
 * tickets, sort price-desc, then for every (buy+get) tickets the
 * cheapest `get` tickets are freed. amount_off is the sum of freed
 * prices. This matches what a customer expects ("the 3 cheapest of
 * every 13 are free") and protects the merchant from a buyer
 * gaming the cart by adding 10 cheap drinks to free 3 expensive ones.
 */
class BuyXGetYCalculator implements PromotionCalculator
{
    public function calculate(PromoCode $promo, Cart $cart, float $subtotalInScope, float $cartSubtotal): ?AppliedPromotion
    {
        $buy = (int) $promo->configValue('buy', 0);
        $get = (int) $promo->configValue('get', 0);
        if ($buy < 1 || $get < 1) {
            return null;
        }

        $tickets = []; // list of unit prices for in-scope items
        foreach ($cart->items as $item) {
            if (!$this->itemMatchesScope($item, $promo)) {
                continue;
            }
            $unitPrice = (float) $item->price;
            for ($i = 0; $i < (int) $item->quantity; $i++) {
                $tickets[] = $unitPrice;
            }
        }

        if (count($tickets) < ($buy + $get)) {
            return null;
        }

        // Sort ascending so the cheapest tickets are the candidates for
        // freebies. We free `get` per `(buy + get)` block.
        sort($tickets);

        $bundleSize = $buy + $get;
        $bundles = intdiv(count($tickets), $bundleSize);
        $amountOff = 0.0;
        for ($b = 0; $b < $bundles; $b++) {
            // First `get` tickets in this bundle's slice are the freebies.
            $sliceStart = $b * $bundleSize;
            for ($g = 0; $g < $get; $g++) {
                $amountOff += $tickets[$sliceStart + $g];
            }
        }

        $amountOff = round(min($amountOff, $subtotalInScope), 2);
        if ($amountOff <= 0) {
            return null;
        }

        return new AppliedPromotion(
            promoCodeId: $promo->id,
            code: $promo->code,
            type: $promo->discount_type,
            nameEn: $promo->getName('en'),
            nameAr: $promo->getName('ar'),
            descriptionEn: $promo->getDescription('en'),
            descriptionAr: $promo->getDescription('ar'),
            amountOff: $amountOff,
            stackable: (bool) $promo->stackable,
            priority: (int) $promo->priority,
        );
    }

    private function itemMatchesScope(\App\Core\Models\CartItem $item, PromoCode $promo): bool
    {
        $product = $item->product;
        if (!$product) {
            return false;
        }

        // No product scope set = match all in the (already store-scoped)
        // cart. With either categories OR products attached, the item
        // must match at least one of them.
        $hasCategoryScope = $promo->categories->isNotEmpty();
        $hasProductScope = $promo->products->isNotEmpty();
        if (!$hasCategoryScope && !$hasProductScope) {
            return true;
        }

        if ($hasProductScope && $promo->products->pluck('id')->contains($product->id)) {
            return true;
        }

        if ($hasCategoryScope && $promo->categories->pluck('id')->contains($product->category_id)) {
            return true;
        }

        return false;
    }
}
