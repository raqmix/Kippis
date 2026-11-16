<?php

namespace App\Http\Resources\Api\V1;

use App\Services\Loyalty\PointsExpiry;
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
            'expiring_soon' => $this->expiringSoonSummary(),
            'transactions' => LoyaltyTransactionResource::collection($this->whenLoaded('transactions')),
        ];
    }

    /**
     * Heads-up payload for the loyalty screen: surface only when at
     * least one earned chunk will expire within the next 30 days AND
     * the customer hasn't already redeemed those points (FIFO).
     *
     * Same maths as ExpireLoyaltyPointsCommand (PointsExpiry) with a
     * now+30d horizon instead of now. Returns null when nothing
     * is at risk — the mobile UI hides the banner entirely in that case.
     */
    private function expiringSoonSummary(): ?array
    {
        $horizon = now()->addDays(30);
        $expiry = app(PointsExpiry::class);

        $atRisk = $expiry->expiringBy($this->resource, $horizon);
        if ($atRisk <= 0) {
            return null;
        }

        $nextExpiry = $expiry->nextExpiryBy($this->resource, $horizon);

        return [
            'points' => $atRisk,
            'expires_at' => $nextExpiry,
        ];
    }
}
