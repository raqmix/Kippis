<?php

namespace App\Http\Resources\Api\V1;

use App\Core\Enums\OrderStatus;
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
        // Map status from enum to translated label
        $statusEnum = OrderStatus::tryFrom($this->status);
        $statusLabel = $statusEnum ? $statusEnum->label() : $this->status;

        return [
            'id' => $this->id,
            'status' => $this->status,
            'status_label' => $statusLabel,
            'total' => (float) $this->total,
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'payment_method' => $this->payment_method, // Keep for backward compatibility
            'payment_method_id' => $this->payment_method_id,
            'payment_method_details' => $this->when($this->relationLoaded('paymentMethod') && $this->paymentMethod, function () {
                return [
                    'id' => $this->paymentMethod->id,
                    'name' => $this->paymentMethod->name,
                    'code' => $this->paymentMethod->code,
                ];
            }),
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

