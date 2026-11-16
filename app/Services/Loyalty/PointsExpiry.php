<?php

namespace App\Services\Loyalty;

use App\Core\Models\LoyaltyTransaction;
use App\Core\Models\LoyaltyWallet;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * How many of a wallet's points will have expired by a cutoff, using the same
 * FIFO accounting as loyalty:expire-points (earned points past their
 * expires_at, minus everything already spent/expired, capped at the balance).
 */
class PointsExpiry
{
    public function expiringBy(LoyaltyWallet $wallet, CarbonInterface $cutoff): int
    {
        $pool = (int) LoyaltyTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('type', LoyaltyTransaction::TYPE_EARNED)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $cutoff)
            ->sum('points');

        if ($pool <= 0) {
            return 0;
        }

        $consumed = (int) LoyaltyTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->whereIn('type', [LoyaltyTransaction::TYPE_REDEEMED, LoyaltyTransaction::TYPE_EXPIRED])
            ->sum(DB::raw('ABS(points)'));

        $consumed += (int) LoyaltyTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('type', LoyaltyTransaction::TYPE_ADJUSTED)
            ->where('points', '<', 0)
            ->sum(DB::raw('ABS(points)'));

        return (int) min(max(0, $pool - $consumed), $wallet->points);
    }
}
