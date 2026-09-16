<?php

namespace App\Services\Promotions\Calculators;

use App\Core\Models\Cart;
use App\Core\Models\PromoCode;
use App\Services\Promotions\AppliedPromotion;

/**
 * "Free delivery" — placeholder calculator. Today the cart has no
 * delivery_fee column (Kippis ships pickup-only at launch); when
 * delivery lands, point this at `cart.delivery_fee` and the rest of
 * the engine wiring continues to just work.
 *
 * Until then the calculator returns a zero-amount AppliedPromotion
 * so the badge ("Free Delivery") still renders on the receipt even
 * though no money changes hands.
 */
class FreeDeliveryCalculator implements PromotionCalculator
{
    public function calculate(PromoCode $promo, Cart $cart, float $subtotalInScope, float $cartSubtotal): ?AppliedPromotion
    {
        $deliveryFee = (float) ($cart->delivery_fee ?? 0);
        $amount = round(min($deliveryFee, $cartSubtotal), 2);

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
