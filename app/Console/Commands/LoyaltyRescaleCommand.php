<?php

namespace App\Console\Commands;

use App\Services\Loyalty\LoyaltyRescaler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * One-off: move loyalty to a coarser point (e.g. 1 point per 10 EGP instead
 * of 1 per EGP) without changing what anyone's points are worth.
 *
 *   php artisan loyalty:rescale --factor=10 --dry-run   # report only
 *   php artisan loyalty:rescale --factor=10             # do it (one transaction)
 *
 * Balances, reward prices and every point amount in settings / QR codes /
 * open carts are divided by the factor, rounded up (min 1 where > 0);
 * points_to_egp_rate is divided rounding down (min 1); loyalty.egp_per_point
 * becomes the old effective EGP-per-point times the factor. See
 * LoyaltyRescaler for the ledger/expiry handling. Output carries ids and
 * aggregates only, never customer details.
 */
class LoyaltyRescaleCommand extends Command
{
    protected $signature = 'loyalty:rescale
        {--factor= : Integer factor to divide point amounts by (>= 2)}
        {--dry-run : Print the before/after report and write nothing}
        {--force : Run even if a rescale was already recorded (factors compound)}';

    protected $description = 'Rescale every point-denominated value (balances, rewards, settings) by a factor, preserving value.';

    public function handle(LoyaltyRescaler $rescaler): int
    {
        $raw = (string) $this->option('factor');
        if (! ctype_digit($raw) || (int) $raw < 2) {
            $this->error('--factor must be an integer >= 2.');

            return self::FAILURE;
        }
        $factor = (int) $raw;
        $dry = (bool) $this->option('dry-run');

        $done = $rescaler->alreadyRescaled();
        if ($done) {
            $msg = "Loyalty was already rescaled (cumulative factor {$done['factor']} at {$done['at']}).";
            if ($dry) {
                $this->warn($msg . ' A real run would be refused without --force.');
            } elseif (! $this->option('force')) {
                $this->error($msg . ' Refusing; pass --force to rescale again (factors compound).');

                return self::FAILURE;
            }
        }

        if ($dry) {
            $plan = $rescaler->plan($factor);
            $wallets = $rescaler->planWallets($factor);
            $this->report($plan, $wallets, true);
            $this->info('[DRY RUN] Nothing was written.');

            return self::SUCCESS;
        }

        $result = $rescaler->apply($factor);
        $this->report($result['plan'], $result['wallets'], false);
        $this->info('Rescale applied.');
        Log::info('LOYALTY_RESCALE', [
            'factor'          => $factor,
            'wallets_changed' => $result['wallets']['wallets_changed'],
            'sum_before'      => $result['wallets']['sum_before'],
            'sum_after'       => $result['wallets']['sum_after'],
        ]);

        return self::SUCCESS;
    }

    private function report(array $plan, array $w, bool $dry): void
    {
        $tag = $dry ? '[DRY RUN] ' : '';
        $f = $plan['factor'];
        $this->line("{$tag}Loyalty rescale, factor {$f}");

        $rate = $plan['earn_rate'];
        $this->newLine();
        $this->line('Earning rate');
        $this->line('  old effective: ' . ($rate['old_egp_per_point'] === null ? 'none' : $this->num($rate['old_egp_per_point']) . ' EGP per point') . "  [from {$rate['source']}]");
        $this->line('  new loyalty.egp_per_point: ' . ($rate['new_egp_per_point'] === null ? 'not set (earning is off)' : $this->num($rate['new_egp_per_point'])) . ' (floor(total / egp_per_point))');

        $this->newLine();
        $this->line('Settings');
        $this->table(['key', 'old', 'new', 'source', 'note'], collect($plan['settings'])->map(fn ($s, $k) => [
            $k, $s['old'], $s['new'] ?? '-', $s['source'], $s['note'] ?? '',
        ])->values()->all());

        $this->itemTable('Redeem items (points_cost, ceil, min 1)', $plan['redeem_items'], ['is_active', 'deleted_at']);
        $this->itemTable('Promo QR codes (points, all rows)', $plan['promo_qr'], ['is_active', 'expires_at']);
        $this->itemTable('QR codes (points_awarded, all rows)', $plan['qr_codes'], ['is_active', 'expires_at']);

        $carts = $plan['carts'];
        $this->newLine();
        $this->line(sprintf(
            'Carts with points_used > 0: %d, points_used sum %d -> %d (ceil; discount is recomputed at checkout)',
            count($carts),
            array_sum(array_column($carts, 'old')),
            array_sum(array_column($carts, 'new')),
        ));

        $this->newLine();
        $this->line('Wallets');
        $this->line("  wallets total: {$w['wallets_total']}");
        $this->line("  wallets with balance > 0: {$w['wallets_with_balance']}");
        $this->line("  wallets whose balance changes: {$w['wallets_changed']}");
        $this->line("  balance sum: {$w['sum_before']} -> {$w['sum_after']}");
        $this->line("  carried over as expiring lots: {$w['expiring_lot_points']}, non-expiring (rounding gain + non-expiring credits): {$w['non_expiring_points']}");
        $this->line("  ledger rows " . ($dry ? 'to write' : 'written') . ": {$w['rows_to_write']}");
        if ($w['wallets_negative'] > 0) {
            $this->warn("  wallets with a negative balance (left untouched): {$w['wallets_negative']}");
        }
        $mismatch = $w['ledger_mismatch_ids'];
        $this->line('  wallets whose ledger sum != balance before the rescale: ' . count($mismatch)
            . (count($mismatch) ? ' (ids: ' . implode(',', array_slice($mismatch, 0, 50)) . (count($mismatch) > 50 ? ',...' : '') . ')' : ''));
    }

    private function itemTable(string $title, array $rows, array $extra): void
    {
        $this->newLine();
        $changed = count(array_filter($rows, fn ($r) => $r['new'] !== $r['old']));
        $this->line(sprintf('%s: %d rows, %d change, sum %d -> %d', $title, count($rows), $changed,
            array_sum(array_column($rows, 'old')), array_sum(array_column($rows, 'new'))));
        if ($rows) {
            $this->table(array_merge(['id', 'old', 'new'], $extra), collect($rows)->map(fn ($r, $id) => array_merge(
                [$id, $r['old'], $r['new']],
                array_map(fn ($e) => $r[$e] === null ? '-' : (string) $r[$e], $extra),
            ))->values()->all());
        }
    }

    private function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.');
    }
}
