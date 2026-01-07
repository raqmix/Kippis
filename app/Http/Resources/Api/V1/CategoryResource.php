<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
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
            'external_source' => $this->external_source,
        ];
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

