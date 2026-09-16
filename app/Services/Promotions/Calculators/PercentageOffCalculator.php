<?php

namespace App\Services\Promotions\Calculators;

use App\Core\Models\Cart;
use App\Core\Models\PromoCode;
use App\Services\Promotions\AppliedPromotion;

class PercentageOffCalculator implements PromotionCalculator
{
    public function calculate(PromoCode $promo, Cart $cart, float $subtotalInScope, float $cartSubtotal): ?AppliedPromotion
    {
        $percent = (float) $promo->discount_value;
        if ($percent <= 0) {
            return null;
        }

        // Percentage applies to the in-scope subtotal (the chunk of the
        // cart matching the promo's category/product scope) so that a
        // "30% off pastries" promo doesn't accidentally discount the
        // customer's espresso.
        $amount = round($subtotalInScope * $percent / 100, 2);
        if ($amount <= 0) {
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
            amountOff: $amount,
            stackable: (bool) $promo->stackable,
            priority: (int) $promo->priority,
        );
    }
}
