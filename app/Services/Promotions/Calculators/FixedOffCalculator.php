<?php

namespace App\Services\Promotions\Calculators;

use App\Core\Models\Cart;
use App\Core\Models\PromoCode;
use App\Services\Promotions\AppliedPromotion;

class FixedOffCalculator implements PromotionCalculator
{
    public function calculate(PromoCode $promo, Cart $cart, float $subtotalInScope, float $cartSubtotal): ?AppliedPromotion
    {
        $amount = (float) $promo->discount_value;
        if ($amount <= 0) {
            return null;
        }

        // Fixed amount is capped at the in-scope subtotal so a 50 EGP
        // off promo doesn't take a 30 EGP cart negative.
        $amount = round(min($amount, $subtotalInScope), 2);
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
