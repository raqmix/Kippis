<?php

namespace App\Console\Commands;

use App\Core\Models\LoyaltyTransaction;
use App\Core\Models\LoyaltyWallet;
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
 *   already_consumed  = sum(absolute value of every redeemed/adjusted-down/expired transaction)
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
        // expired_pool: sum of earned amounts whose expires_at has
        // passed. Without this clamp the formula below would also
        // expire still-valid earns when the wallet has been heavily
        // redeemed.
        $expiredPool = (int) LoyaltyTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('type', LoyaltyTransaction::TYPE_EARNED)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->sum('points');

        if ($expiredPool <= 0) {
            return 0;
        }

        // already_consumed: every absolute point that has left the
        // wallet, in any way (redeem, admin-adjusted-down, prior expiry).
        // We use abs() because redeem rows are stored as negative.
        $alreadyConsumed = (int) LoyaltyTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->whereIn('type', [
                LoyaltyTransaction::TYPE_REDEEMED,
                LoyaltyTransaction::TYPE_EXPIRED,
            ])
            ->sum(DB::raw('ABS(points)'));

        // Admin adjustments that were negative also count as consumed.
        $alreadyConsumed += (int) LoyaltyTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('type', LoyaltyTransaction::TYPE_ADJUSTED)
            ->where('points', '<', 0)
            ->sum(DB::raw('ABS(points)'));

        $newlyExpiring = max(0, $expiredPool - $alreadyConsumed);

        // Hard floor: never drive the wallet below 0 via expiry.
        return (int) min($newlyExpiring, $wallet->points);
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
