<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QrReceiptResource extends JsonResource
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
            'receipt_number' => $this->receipt_number,
            'amount' => (float) $this->amount,
            'status' => $this->status,
            'points_requested' => $this->points_requested,
            'points_awarded' => $this->points_awarded,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

