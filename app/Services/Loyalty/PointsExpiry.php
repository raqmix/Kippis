<?php

namespace App\Services\Loyalty;

use App\Core\Models\LoyaltyTransaction;
use App\Core\Models\LoyaltyWallet;
use App\Core\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * FIFO point-expiry accounting, shared by loyalty:expire-points, the
 * lifecycle pushes, the wallet API's `expiring_soon` banner and
 * loyalty:rescale.
 *
 *   pool(t)   = sum(positive rows with expires_at <= t)
 *   consumed  = sum(|points|) of every negative row (redeemed, expired,
 *               admin adjusted down, refund deducted)
 *   expiring  = min(max(0, pool(t) - consumed), balance)
 *
 * Ledger window: only rows AFTER the wallet's last loyalty rescale count
 * (and after the global epoch `loyalty.ledger_epoch_tx_id` the rescale
 * stores). Rows before that are in the old point scale; the rescale closes
 * them out and re-issues the still-unexpired earn lots in the new scale
 * (type 'rescale', reference_type loyalty_rescale_lot, original expires_at).
 * Without the window, old-scale earns/redeems would be mixed with
 * new-scale ones and expiry could take far more (or less) than it should.
 */
class PointsExpiry
{
    public const EPOCH_SETTING = 'loyalty.ledger_epoch_tx_id';

    /** Rows with id > this id are in the wallet's current point scale. */
    public function windowStart(LoyaltyWallet $wallet): int
    {
        $epoch = (int) Setting::get(self::EPOCH_SETTING, 0);
        $close = (int) LoyaltyTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('reference_type', LoyaltyTransaction::REF_RESCALE_CLOSE)
            ->max('id');

        return max($epoch, $close);
    }

    public function expiringBy(LoyaltyWallet $wallet, CarbonInterface $cutoff): int
    {
        $from = $this->windowStart($wallet);

        $pool = (int) $this->expiringRows($wallet, $from)
            ->where('expires_at', '<=', $cutoff)
            ->sum('points');

        if ($pool <= 0) {
            return 0;
        }

        return (int) min(max(0, $pool - $this->consumed($wallet, $from)), (int) $wallet->points);
    }

    /** Earliest expires_at (<= $horizon) among the window's expiring rows. */
    public function nextExpiryBy(LoyaltyWallet $wallet, CarbonInterface $horizon): mixed
    {
        return $this->expiringRows($wallet, $this->windowStart($wallet))
            ->where('expires_at', '<=', $horizon)
            ->min('expires_at');
    }

    /**
     * The still-held portion of every expiring lot, oldest expiry first:
     * consumption is taken off the earliest-expiring lots (same FIFO the
     * sweep uses), and the total is capped at the balance.
     *
     * @return Collection<int, array{expires_at: \Illuminate\Support\Carbon, points: int}>
     */
    public function remainingLots(LoyaltyWallet $wallet): Collection
    {
        $from = $this->windowStart($wallet);
        $rows = $this->expiringRows($wallet, $from)
            ->orderBy('expires_at')->orderBy('id')
            ->get(['id', 'points', 'expires_at']);

        $toEat = $this->consumed($wallet, $from);
        $total = (int) $rows->sum('points');
        // Defensive: never report more expiring points than the wallet holds.
        $toEat = max($toEat, $total - max(0, (int) $wallet->points));

        $lots = collect();
        foreach ($rows as $row) {
            $points = (int) $row->points;
            $eat = min($toEat, $points);
            $toEat -= $eat;
            if ($points - $eat > 0) {
                $lots->push(['expires_at' => $row->expires_at, 'points' => $points - $eat]);
            }
        }

        return $lots;
    }

    private function expiringRows(LoyaltyWallet $wallet, int $from): Builder
    {
        return LoyaltyTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('id', '>', $from)
            ->whereIn('type', [LoyaltyTransaction::TYPE_EARNED, LoyaltyTransaction::TYPE_RESCALE])
            ->where('points', '>', 0)
            ->whereNotNull('expires_at');
    }

    /** Every point that has left the wallet inside the window (rescale rows excluded). */
    private function consumed(LoyaltyWallet $wallet, int $from): int
    {
        return (int) -LoyaltyTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('id', '>', $from)
            ->where('points', '<', 0)
            ->where('type', '!=', LoyaltyTransaction::TYPE_RESCALE)
            ->sum('points');
    }
}
