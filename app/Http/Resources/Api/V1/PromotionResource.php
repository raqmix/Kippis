<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PromotionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'offer_text' => $this->offer_text,
            'image' => $this->image ? (str_starts_with($this->image, 'http') ? $this->image : asset(Storage::url($this->image))) : null,
            'cta_text' => $this->cta_text,
            'cta_link' => $this->cta_link,
            'dismiss_text' => $this->dismiss_text,
            'product' => $this->whenLoaded('product', fn () => $this->product ? [
                'id' => $this->product->id,
                'name' => $this->product->getName(app()->getLocale()),
                'name_ar' => $this->product->getName('ar'),
                'name_en' => $this->product->getName('en'),
                'description' => $this->product->getDescription(app()->getLocale()),
                'description_ar' => $this->product->getDescription('ar'),
                'description_en' => $this->product->getDescription('en'),
                'image' => $this->product->image ? (str_starts_with($this->product->image, 'http') ? $this->product->image : asset(Storage::url($this->product->image))) : null,
                'base_price' => (float) $this->product->base_price,
            ] : null),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
        ];
    }
}
