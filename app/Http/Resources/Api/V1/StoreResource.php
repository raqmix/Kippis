<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'name_localized' => $this->name_localized,
            'address' => $this->address,
            'latitude' => $this->latitude ? (string) $this->latitude : null,
            'longitude' => $this->longitude ? (string) $this->longitude : null,
            'open_time' => $this->open_time,
            'close_time' => $this->close_time,
            'is_open_now' => $this->is_open_now ?? $this->isOpenNow(),
        ];

        // Include distance if calculated
        if (isset($this->distance)) {
            $data['distance'] = round($this->distance, 2); // Distance in kilometers
        }

        return $data;
    }
}
