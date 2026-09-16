<?php

namespace App\Services\Promotions;

/**
 * Aggregated output of PromotionEvaluator::evaluate().
 *
 * `appliedPromotions` is ordered priority-desc: the first element is
 * the strongest match. `totalDiscount` is the sum of every applied
 * promo's amount_off and is what `Cart::recalculate()` writes to
 * `carts.discount` / used to compute `carts.total`.
 */
class EvaluationResult
{
    /**
     * @param  array<int, AppliedPromotion>  $appliedPromotions
     */
    public function __construct(
        public readonly array $appliedPromotions,
        public readonly float $totalDiscount,
    ) {
    }

    public static function empty(): self
    {
        return new self([], 0.0);
    }

    /**
     * @return array<int, array>  list of toArray() snapshots, ready
     *                            to drop into the JSON column.
     */
    public function snapshot(): array
    {
        return array_map(fn (AppliedPromotion $p) => $p->toArray(), $this->appliedPromotions);
    }
}
