<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoyaltyWalletResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'points' => $this->points,
            'qr_token' => $this->qr_token,
            'transactions' => LoyaltyTransactionResource::collection($this->whenLoaded('transactions')),
        ];
    }
}

