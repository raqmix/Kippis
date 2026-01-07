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
            'image' => $this->getImageUrl(),
            'base_price' => (float) $this->base_price,
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->getName(app()->getLocale()),
                ];
            }),
            'external_source' => $this->external_source,
            'modifiers' => $this->getModifiersGroupedByType(),
        ];
    }

    /**
     * Get modifiers grouped by type.
     *
     * @return array<string, array>
     */
    private function getModifiersGroupedByType(): array
    {
        $modifiers = $this->addonModifiers->groupBy('type');

        $result = [
            'size' => [],
            'smothing' => [],
            'customize_modifires' => [],
            'extra' => [],
        ];

        foreach (['size', 'smothing', 'customize_modifires', 'extra'] as $type) {
            $typeModifiers = $modifiers->get($type, collect());
            $result[$type] = $typeModifiers->map(function ($modifier) {
                return [
                    'id' => $modifier->id,
                    'type' => $modifier->type,
                    'name' => $modifier->getName(app()->getLocale()),
                    'name_ar' => $modifier->getName('ar'),
                    'name_en' => $modifier->getName('en'),
                    'max_level' => $modifier->max_level,
                    'price' => (float) $modifier->price,
                ];
            })->values()->all();
        }

        return $result;
    }

    /**
     * Get the image URL, handling both local and external (Foodics) images.
     *
     * @return string|null
     */
    private function getImageUrl(): ?string
    {
        if (!$this->image) {
            return null;
        }

        // If the image is already a full URL (starts with http:// or https://), return as is
        if (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')) {
            return $this->image;
        }

        // Otherwise, it's a local image, prepend storage path
        return asset('storage/' . $this->image);
    }
}

