<?php

namespace App\Http\Resources\Api\V1;

use App\Core\Models\CustomerSpendReward;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shape returned by GET /v1/spend-rewards — one row per active voucher
 * the customer holds. Includes the tier's choice groups (with product
 * options resolved) so the Flutter side can render the picker without
 * extra fetches.
 */
class SpendRewardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var CustomerSpendReward $this */
        $tier = $this->tier;

        return [
            'id' => $this->id,
            'status' => $this->status,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'picked_at' => $this->picked_at?->toIso8601String(),
            'applied_at' => $this->applied_at?->toIso8601String(),
            'picks' => $this->picks_json ?? [],
            'tier' => $tier ? [
                'id' => $tier->id,
                'name_en' => $tier->name_en,
                'name_ar' => $tier->name_ar,
                'description_en' => $tier->description_en,
                'description_ar' => $tier->description_ar,
                'threshold_piasters' => (int) $tier->threshold_piasters,
                'cycle' => $tier->cycle,
                'choice_groups' => $this->resolveChoiceGroups($tier->choice_groups ?? []),
            ] : null,
        ];
    }

    /**
     * Enrich each choice_group with the actual product options the
     * customer can pick from. Runs once per resource; N+1 is bounded
     * by the number of categories on the tier.
     */
    private function resolveChoiceGroups(array $groups): array
    {
        return array_map(function ($group) {
            $categoryIds = array_map('intval', $group['category_ids'] ?? []);
            $products = empty($categoryIds)
                ? collect()
                : \App\Core\Models\Product::query()
                    ->whereIn('category_id', $categoryIds)
                    ->orderBy('id')
                    ->get(['id', 'name_json', 'image', 'category_id']);

            return [
                'label_en' => $group['label_en'] ?? '',
                'label_ar' => $group['label_ar'] ?? '',
                'quantity' => (int) ($group['quantity'] ?? 1),
                'category_ids' => $categoryIds,
                'products' => $products->map(fn ($p) => [
                    'id' => $p->id,
                    'name_en' => is_array($p->name_json) ? ($p->name_json['en'] ?? '') : '',
                    'name_ar' => is_array($p->name_json) ? ($p->name_json['ar'] ?? '') : '',
                    'image' => $p->image,
                    'category_id' => $p->category_id,
                ])->values(),
            ];
        }, $groups);
    }
}
