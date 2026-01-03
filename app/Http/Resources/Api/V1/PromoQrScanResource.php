<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromoQrScanResource extends JsonResource
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
            'code' => $this->promoQrCode->code,
            'name' => $this->promoQrCode->name,
            'points_awarded' => $this->points_awarded,
            'scanned_at' => $this->scanned_at->toIso8601String(),
        ];
    }
}

