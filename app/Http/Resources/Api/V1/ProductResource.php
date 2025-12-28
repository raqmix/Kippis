<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'name' => $this->getName(app()->getLocale()),
            'name_ar' => $this->getName('ar'),
            'name_en' => $this->getName('en'),
            'description' => $this->getDescription(app()->getLocale()),
            'description_ar' => $this->getDescription('ar'),
            'description_en' => $this->getDescription('en'),
            'image' => $this->image ? asset('storage/' . $this->image) : null,
            'base_price' => (float) $this->base_price,
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->getName(app()->getLocale()),
                ];
            }),
            'external_source' => $this->external_source,
        ];
    }
}

