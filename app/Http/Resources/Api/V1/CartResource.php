<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'items' => CartItemResource::collection($this->whenLoaded('items')),
            'promo_code' => $this->when($this->relationLoaded('promoCode') && $this->promoCode, function () {
                return [
                    'code' => $this->promoCode->code,
                    'discount' => (float) $this->discount,
                ];
            }),
            'wallet_item' => $this->when(
                $this->wallet_item_id && $this->relationLoaded('walletItem') && $this->walletItem,
                function () {
                    return [
                        'id' => $this->walletItem->id,
                        'title' => $this->walletItem->getTitle(app()->getLocale()),
                        'product_id' => $this->walletItem->redeemItem?->product_id,
                        'discount' => (float) $this->wallet_discount,
                        'reward_type' => $this->walletItem->reward_type,
                        'source' => $this->walletItem->source,
                    ];
                },
            ),
            'points_used'    => (int) $this->points_used,
            'points_discount' => (float) $this->points_discount,
            'applied_promotions' => is_array($this->applied_promotions_snapshot)
                ? $this->applied_promotions_snapshot
                : [],
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'total' => (float) $this->total,
            // App-only discount (additive fields; older apps ignore them).
            // original_total = price before any discount, for the
            // crossed-out price.
            'original_total' => (float) $this->subtotal,
            'platform' => $this->platform,
            'app_discount' => (float) $this->app_discount,
            'cup_card_discount' => (float) $this->cup_card_discount,
            'app_discount_percent' => $this->appDiscountPercent(),
            'promo_discount' => (float) $this->promo_discount,
            'discount_source' => $this->discount_source,
            'app_offer' => $this->appOfferBlock(),
            'app_offer_snapshot' => $this->app_offer_snapshot ?: null,
        ];
    }

    private function appDiscountPercent(): float
    {
        $snap = is_array($this->app_offer_snapshot) ? $this->app_offer_snapshot : [];

        return (float) ($snap['percent'] ?? 0);
    }

    /**
     * {eligible, percent, applied, beats_promotion, suppressed_promotions[],
     *  message} — message is set when a promo code / offer was entered but
     * the app discount is the larger one, e.g. "Your 30% app discount is
     * better than this code, so we kept it."
     */
    private function appOfferBlock(): array
    {
        $snap = is_array($this->app_offer_snapshot) ? $this->app_offer_snapshot : [];
        $percent = (float) ($snap['percent'] ?? 0);
        $suppressed = array_values((array) ($snap['suppressed_promotions'] ?? []));
        $applied = $this->discount_source === \App\Core\Models\Cart::DISCOUNT_SOURCE_APP_OFFER;
        $beatsPromotion = $applied && ! empty($suppressed);

        $message = null;
        if ($beatsPromotion) {
            $percentLabel = floor($percent) == $percent ? (string) (int) $percent : (string) $percent;
            $message = __('app_offer.beats_promotion', ['percent' => $percentLabel]);
        }

        return [
            'eligible' => (bool) ($snap['eligible'] ?? false),
            'percent' => $percent,
            'applied' => $applied,
            'beats_promotion' => $beatsPromotion,
            'promotion_discount_candidate' => (float) ($snap['promo_discount'] ?? 0),
            'suppressed_promotions' => $suppressed,
            'message' => $message,
        ];
    }
}

