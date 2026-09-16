<?php

namespace App\Http\Resources\Api\V1;

use App\Core\Models\LoyaltyTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

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
     * Mirrors ExpireLoyaltyPointsCommand::computeExpiringForWallet but
     * with a now+30d horizon instead of now. Returns null when nothing
     * is at risk — the mobile UI hides the banner entirely in that case.
     */
    private function expiringSoonSummary(): ?array
    {
        $horizon = now()->addDays(30);

        $pool = (int) LoyaltyTransaction::query()
            ->where('wallet_id', $this->id)
            ->where('type', LoyaltyTransaction::TYPE_EARNED)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $horizon)
            ->sum('points');

        if ($pool <= 0) {
            return null;
        }

        $consumed = (int) LoyaltyTransaction::query()
            ->where('wallet_id', $this->id)
            ->whereIn('type', [
                LoyaltyTransaction::TYPE_REDEEMED,
                LoyaltyTransaction::TYPE_EXPIRED,
            ])
            ->sum(DB::raw('ABS(points)'));

        $consumed += (int) LoyaltyTransaction::query()
            ->where('wallet_id', $this->id)
            ->where('type', LoyaltyTransaction::TYPE_ADJUSTED)
            ->where('points', '<', 0)
            ->sum(DB::raw('ABS(points)'));

        $atRisk = max(0, $pool - $consumed);
        $atRisk = (int) min($atRisk, (int) $this->points);

        if ($atRisk <= 0) {
            return null;
        }

        $nextExpiry = LoyaltyTransaction::query()
            ->where('wallet_id', $this->id)
            ->where('type', LoyaltyTransaction::TYPE_EARNED)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $horizon)
            ->min('expires_at');

        return [
            'points' => $atRisk,
            'expires_at' => $nextExpiry,
        ];
    }
}
