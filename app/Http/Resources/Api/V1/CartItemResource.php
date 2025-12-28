<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
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
            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => $this->product->id,
                    'name' => $this->product->getName(app()->getLocale()),
                    'image' => $this->product->image ? asset('storage/' . $this->product->image) : null,
                ];
            }),
            'quantity' => $this->quantity,
            'price' => (float) $this->price,
            'modifiers' => $this->modifiers_snapshot,
        ];
    }
}

