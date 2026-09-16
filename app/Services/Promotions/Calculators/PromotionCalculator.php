<?php

namespace App\Services\Promotions\Calculators;

use App\Core\Models\Cart;
use App\Core\Models\PromoCode;
use App\Services\Promotions\AppliedPromotion;

/**
 * Common shape for every promotion type's calculator. The evaluator
 * fans out gating (scope, conditions, validity, segments) so each
 * calculator only needs to answer "given this cart + promo, what's
 * the actual discount amount?".
 *
 * `calculate()` may return null when, even after the evaluator's
 * gates pass, the promo can't produce any discount given the cart
 * (e.g. buy_x_get_y with only 2 items when buy=10).
 */
interface PromotionCalculator
{
    public function calculate(PromoCode $promo, Cart $cart, float $subtotalInScope, float $cartSubtotal): ?AppliedPromotion;
}
