<?php

namespace App\Services\Promotions\Calculators;

use App\Core\Models\Cart;
use App\Core\Models\Product;
use App\Core\Models\PromoCode;
use App\Services\Promotions\AppliedPromotion;

/**
 * "Free X with any cart" — admin picks a specific product (or set)
 * to gift on top of the cart. Config:
 *   {product_id: 42, quantity: 1}
 * Or for multiple options:
 *   {product_ids: [42, 43], quantity: 1}
 *
 * Free items render in the receipt as 0-EGP lines tagged with the
 * promo's name. amount_off = sum of the gifted products' base_price
 * so the cart "total" reflects the perceived value handed over.
 */
class FreeItemCalculator implements PromotionCalculator
{
    public function calculate(PromoCode $promo, Cart $cart, float $subtotalInScope, float $cartSubtotal): ?AppliedPromotion
    {
        $quantity = max(1, (int) $promo->configValue('quantity', 1));

        $productIds = $promo->configValue('product_ids', []);
        if (!is_array($productIds) || empty($productIds)) {
            $singleId = $promo->configValue('product_id');
            if (!$singleId) {
                return null;
            }
            $productIds = [(int) $singleId];
        }

        $products = Product::whereIn('id', $productIds)->get();
        if ($products->isEmpty()) {
            return null;
        }

        $freeItems = [];
        $amountOff = 0.0;
        foreach ($products as $product) {
            $unitPrice = (float) $product->base_price;
            $freeItems[] = [
                'product_id' => $product->id,
                'name' => $product->getName(app()->getLocale() ?: 'en'),
                'quantity' => $quantity,
                'price' => $unitPrice,
            ];
            $amountOff += $unitPrice * $quantity;
        }

        $amountOff = round($amountOff, 2);
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
            freeItems: $freeItems,
            stackable: (bool) $promo->stackable,
            priority: (int) $promo->priority,
        );
    }
}
