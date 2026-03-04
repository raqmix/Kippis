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
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
        ];
    }
}
