<?php

namespace App\Services\Promotions;

/**
 * Frozen snapshot of a single promotion that survived evaluation
 * against the cart. The cart's `applied_promotions_snapshot` JSON
 * column holds an array of these in their `toArray()` form, which is
 * also what flows out via CartResource to the mobile app.
 *
 * Keeping this as a small value object (not an Eloquent model) lets
 * the evaluator return rich results from in-memory computations
 * without polluting the DB with throwaway rows.
 */
class AppliedPromotion
{
    /**
     * @param  array<int, array{product_id: int, name: string, quantity: int, price: float}>  $freeItems
     */
    public function __construct(
        public readonly int $promoCodeId,
        public readonly ?string $code,
        public readonly string $type,
        public readonly string $nameEn,
        public readonly string $nameAr,
        public readonly string $descriptionEn,
        public readonly string $descriptionAr,
        public readonly float $amountOff,
        public readonly array $freeItems = [],
        public readonly bool $stackable = false,
        public readonly int $priority = 0,
    ) {
    }

    public function toArray(): array
    {
        return [
            'promo_code_id' => $this->promoCodeId,
            'code' => $this->code,
            'type' => $this->type,
            'name' => [
                'en' => $this->nameEn,
                'ar' => $this->nameAr,
            ],
            'description' => [
                'en' => $this->descriptionEn,
                'ar' => $this->descriptionAr,
            ],
            'amount_off' => round($this->amountOff, 2),
            'free_items' => $this->freeItems,
            'stackable' => $this->stackable,
            'priority' => $this->priority,
        ];
    }
}
