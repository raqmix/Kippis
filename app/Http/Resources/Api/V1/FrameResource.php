<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FrameResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fileHelper = new \App\Helpers\FileHelper();

        return [
            'id' => $this->id,
            'name' => $this->getName(app()->getLocale()),
            'name_ar' => $this->getName('ar'),
            'name_en' => $this->getName('en'),
            'thumbnail_url' => $this->thumbnail_path ? $fileHelper->getUrl($this->thumbnail_path, 'public') : null,
            'overlay_url' => $this->overlay_path ? $fileHelper->getUrl($this->overlay_path, 'public') : null,
        ];
    }
}
