<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        $translation = $this->translation($locale);

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'type' => $this->type,
            'is_active' => $this->is_active,
            'version' => $this->version,
            'title' => $translation?->title ?? '',
            'content' => $translation?->content ?? '',
            'title_ar' => $this->translation('ar')?->title ?? '',
            'content_ar' => $this->translation('ar')?->content ?? '',
            'title_en' => $this->translation('en')?->title ?? '',
            'content_en' => $this->translation('en')?->content ?? '',
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

