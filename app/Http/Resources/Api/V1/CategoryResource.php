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
            'image' => $this->image ? asset('storage/' . $this->image) : null,
            'external_source' => $this->external_source,
        ];
    }
}

