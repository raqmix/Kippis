<?php

namespace App\Http\Resources\Api\V1;

use App\Core\Models\PromoCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Customer-facing shape of a browsable promo code. Reads the row's
 * bilingual copy, discount summary, minimum-order threshold, validity
 * window, and code (nullable — auto-apply promos have no code to type).
 *
 * DO NOT surface internal-only fields (priority, usage_limit, config,
 * conditions, id/updated_at/etc.) — keep the payload just what the
 * client needs to render a card.
 */
class OfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var PromoCode $this */
        $locale = $request->query('locale', app()->getLocale());

        return [
            'id' => $this->id,
            'code' => $this->code,
            'auto_apply' => (bool) $this->auto_apply,
            'name' => [
                'en' => $this->getName('en'),
                'ar' => $this->getName('ar'),
            ],
            'description' => [
                'en' => $this->getDescription('en'),
                'ar' => $this->getDescription('ar'),
            ],
            // Best-effort human summary — customers want to see "20% off"
            // or "Free item" at a glance, not deep JSON.
            'discount_summary' => $this->discountSummary($locale),
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value !== null
                ? (float) $this->discount_value
                : null,
            'minimum_order_amount' => $this->minimum_order_amount !== null
                ? (float) $this->minimum_order_amount
                : null,
            'valid_from' => $this->valid_from?->toIso8601String(),
            'valid_to' => $this->valid_to?->toIso8601String(),
        ];
    }

    private function discountSummary(string $locale): string
    {
        $ar = $locale === 'ar';
        return match ($this->discount_type) {
            PromoCode::TYPE_PERCENTAGE => $ar
                ? "خصم {$this->discount_value}٪"
                : "{$this->discount_value}% off",
            PromoCode::TYPE_FIXED => $ar
                ? "خصم {$this->discount_value} ج.م"
                : "EGP {$this->discount_value} off",
            PromoCode::TYPE_BUY_X_GET_Y => $ar
                ? 'اشترِ واحصل مجاناً'
                : 'Buy one, get one free',
            PromoCode::TYPE_FREE_ITEM => $ar ? 'منتج مجاني' : 'Free item',
            PromoCode::TYPE_FREE_DELIVERY => $ar ? 'توصيل مجاني' : 'Free delivery',
            default => '',
        };
    }
}
