<?php

namespace App\Console\Commands;

use App\Core\Models\LoyaltyTransaction;
use App\Core\Models\LoyaltyWallet;
use App\Services\Loyalty\PointsExpiry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Daily loyalty-point expiry sweep.
 *
 * For each wallet that has at least one earned transaction whose
 * `expires_at` has passed, we compute how many points should be expired
 * NOW using FIFO accounting:
 *
 *   expired_pool      = sum(earned.points where expires_at <= now)
 *   already_consumed  = sum(absolute value of every negative transaction:
 *                       redeemed, adjusted down, expired, refund-deducted)
 *
 * Only the ledger rows after the wallet's last loyalty:rescale count (see
 * PointsExpiry): older rows are in the previous point scale.
 *   newly_expiring    = max(0, expired_pool - already_consumed)
 *
 * In other words: of every point that has crossed its expiry date, only
 * the portion that the customer hasn't already redeemed counts as
 * "expiring today". This naturally treats the OLDEST earns as having
 * been spent first — the customer never loses points they earned
 * recently because of an old earn that has since been redeemed.
 *
 * We cap `newly_expiring` at the wallet's current balance as a defensive
 * floor (so a bug in addPoints/deductPoints can't drive the wallet
 * negative via expiry).
 *
 * Usage:
 *   php artisan loyalty:expire-points          # do it for real
 *   php artisan loyalty:expire-points --dry    # report what would expire
 *   php artisan loyalty:expire-points --wallet=42  # one wallet only
 */
class ExpireLoyaltyPointsCommand extends Command
{
    protected $signature = 'loyalty:expire-points
        {--dry : Report what would expire without writing anything}
        {--wallet= : Only process a single wallet id}';

    protected $description = 'Expire loyalty points whose earned-by date has passed (FIFO).';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $walletId = $this->option('wallet');
        $now = now();

        $query = LoyaltyWallet::query();
        if ($walletId) {
            $query->whereKey($walletId);
        }

        $totalExpired = 0;
        $walletsTouched = 0;

        $query->chunkById(50, function ($wallets) use (&$totalExpired, &$walletsTouched, $now, $dry) {
            foreach ($wallets as $wallet) {
                $expiring = $this->computeExpiringForWallet($wallet, $now);
                if ($expiring <= 0) {
                    continue;
                }

                $walletsTouched++;
                $totalExpired += $expiring;

                if ($dry) {
                    $this->line(sprintf(
                        '[DRY] wallet#%d → %d points would expire (balance %d → %d)',
                        $wallet->id,
                        $expiring,
                        $wallet->points,
                        max(0, $wallet->points - $expiring),
                    ));
                    continue;
                }

                $this->expireForWallet($wallet, $expiring, $now);
                $this->line(sprintf(
                    'wallet#%d → expired %d points (balance now %d)',
                    $wallet->id,
                    $expiring,
                    $wallet->refresh()->points,
                ));
            }
        });

        $tag = $dry ? '[DRY] ' : '';
        $this->info("{$tag}Done. {$walletsTouched} wallet(s) touched, {$totalExpired} points expired.");
        Log::info('LOYALTY_POINTS_EXPIRY_RUN', [
            'dry' => $dry,
            'wallets_touched' => $walletsTouched,
            'total_expired' => $totalExpired,
        ]);

        return self::SUCCESS;
    }

    private function computeExpiringForWallet(LoyaltyWallet $wallet, $now): int
    {
        // FIFO maths (and the post-rescale ledger window) live in
        // PointsExpiry so the sweep, the wallet API banner and the
        // lifecycle pushes can never disagree.
        return app(PointsExpiry::class)->expiringBy($wallet, $now);
    }

    private function expireForWallet(LoyaltyWallet $wallet, int $amount, $now): void
    {
        DB::transaction(function () use ($wallet, $amount, $now) {
            $locked = LoyaltyWallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            // Recompute under lock — a redeem may have raced in between
            // the chunk read and the lock, so the wallet might not have
            // the points we expected to expire anymore. Re-run the
            // formula and clamp again.
            $finalAmount = $this->computeExpiringForWallet($locked, $now);
            if ($finalAmount <= 0) {
                return;
            }

            $locked->decrement('points', $finalAmount);
            $locked->transactions()->create([
                'type' => LoyaltyTransaction::TYPE_EXPIRED,
                'points' => -$finalAmount,
                'description' => '1-year expiry sweep',
            ]);

            \App\Events\LoyaltyWalletUpdated::dispatch($locked->refresh(), LoyaltyTransaction::TYPE_EXPIRED);
        });
    }
}
