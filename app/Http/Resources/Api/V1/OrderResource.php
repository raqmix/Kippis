<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'status' => $this->status,
            'total' => (float) $this->total,
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'payment_method' => $this->payment_method,
            'pickup_code' => $this->pickup_code,
            'items' => $this->items_snapshot,
            'modifiers' => $this->modifiers_snapshot,
            'promo_code' => $this->when($this->relationLoaded('promoCode') && $this->promoCode, function () {
                return [
                    'code' => $this->promoCode->code,
                    'discount' => (float) $this->promo_discount,
                ];
            }),
            'store' => $this->whenLoaded('store', function () {
                return [
                    'id' => $this->store->id,
                    'name' => $this->store->name,
                ];
            }),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

