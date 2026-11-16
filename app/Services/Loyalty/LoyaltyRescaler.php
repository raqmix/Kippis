<?php

namespace App\Services\Loyalty;

use App\Core\Models\Cart;
use App\Core\Models\LoyaltyTransaction;
use App\Core\Models\LoyaltyWallet;
use App\Core\Models\PromoQrCode;
use App\Core\Models\QrCode;
use App\Core\Models\RedeemItem;
use App\Core\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Rescales every point-denominated value by an integer factor (e.g. 10 when
 * earning moves from "1 point per EGP" to "1 point per 10 EGP"), keeping each
 * customer's value: balances and prices round in the customer's favour.
 *
 * Ledger: per wallet with a balance, one 'rescale' close-out row (-old
 * balance, reference_type loyalty_rescale_close) followed by 'rescale' rows
 * re-issuing the still-unexpired earn lots in the new scale with their
 * original expires_at (reference_type loyalty_rescale_lot), plus one
 * non-expiring row for the rounding remainder. sum(ledger) still equals the
 * balance, and PointsExpiry only looks at rows after the close-out (and after
 * the global epoch for wallets at 0), so expiry runs purely in the new scale.
 *
 * Negative balances (debts) are rescaled toward zero, -intdiv(|old|, f), so
 * a debt never grows: close-out row +|old|, then one 'rescale' row with the
 * new (negative) balance.
 *
 * plan() only reads; apply() writes everything in one DB transaction and must
 * run with the app in maintenance mode, workers and scheduler stopped (the
 * order/redeem/checkout paths compute point amounts before taking the wallet
 * lock). A second run is refused, also when two runs race: the marker is
 * re-checked inside the transaction under a row lock. There is deliberately
 * no way to rescale twice (refund reversal assumes a single factor).
 */
class LoyaltyRescaler
{
    public const MARKER_FACTOR = 'loyalty.rescaled_factor';
    public const MARKER_AT = 'loyalty.rescaled_at';
    /** Guard row that concurrent runs serialize on (SELECT ... FOR UPDATE). */
    public const LOCK_KEY = 'loyalty.rescale_lock';

    /**
     * Point-denominated amounts: key => [default when no Setting row, group, rule].
     * Defaults are the same fallbacks the reading code uses.
     * rule ceil: ceil(v / f), at least 1 when v > 0 (0 stays 0 = off/unlimited).
     */
    public static function pointSettings(): array
    {
        return [
            'loyalty.welcome_bonus_points'  => [(int) config('core.loyalty.welcome_bonus_points', 100), 'loyalty'],
            'check_in.daily_points'         => [3, 'check_in'],
            'check_in.streak_3_bonus'       => [5, 'check_in'],
            'check_in.streak_7_bonus'       => [20, 'check_in'],
            'check_in.streak_14_bonus'      => [15, 'check_in'],
            'referral.inviter_points'       => [30, 'referral'],
            'referral.invitee_points'       => [30, 'referral'],
            'feedback.points_per_rating'    => [5, 'feedback'],
            'loyalty.max_points_per_order'  => [0, 'loyalty'],
            'loyalty.close_to_gift_points'  => [10, 'loyalty'],
        ];
    }

    public function __construct(
        private PointsExpiry $expiry,
        private PointsEarning $earning,
    ) {}

    public static function ceilDiv(int $value, int $factor): int
    {
        if ($value <= 0) {
            return 0;
        }

        return max(1, intdiv($value + $factor - 1, $factor));
    }

    public function alreadyRescaled(): ?array
    {
        $factor = Setting::get(self::MARKER_FACTOR);
        if ($factor === null) {
            return null;
        }

        return ['factor' => (int) $factor, 'at' => (string) Setting::get(self::MARKER_AT, '')];
    }

    /** Everything except wallets (those are planned per wallet, under lock in apply()). */
    public function plan(int $factor): array
    {
        return [
            'factor'       => $factor,
            'earn_rate'    => $this->planEarnRate($factor),
            'settings'     => $this->planSettings($factor),
            'redeem_items' => $this->planColumn(RedeemItem::withTrashed(), 'points_cost', $factor, ['is_active', 'deleted_at']),
            'promo_qr'     => $this->planColumn(PromoQrCode::query(), 'points', $factor, ['is_active', 'expires_at']),
            'qr_codes'     => $this->planColumn(QrCode::query(), 'points_awarded', $factor, ['is_active', 'expires_at']),
            'carts'        => $this->planColumn(Cart::query(), 'points_used', $factor, []),
        ];
    }

    /**
     * New-scale ledger rows for one wallet.
     *
     * @return array{old:int, new:int, lots:array<int, array{expires_at:mixed, points:int}>, residue:int, ledger_sum:int}
     */
    public function planWallet(LoyaltyWallet $wallet, int $factor): array
    {
        $old = (int) $wallet->points;
        $ledgerSum = (int) $wallet->transactions()->sum('points');
        if ($old < 0) {
            // A debt rounds toward zero so it never grows.
            return ['old' => $old, 'new' => -intdiv(-$old, $factor), 'lots' => [], 'residue' => 0, 'ledger_sum' => $ledgerSum];
        }
        $new = self::ceilDiv($old, $factor);

        // Group the still-held earn lots by expiry day (latest time of the
        // day wins), then convert with cumulative floors so the expiring
        // part is floor(total / f) and the rounding gain is one
        // non-expiring remainder row.
        $byDay = [];
        foreach ($this->expiry->remainingLots($wallet) as $lot) {
            $day = $lot['expires_at']->format('Y-m-d');
            if (! isset($byDay[$day])) {
                $byDay[$day] = ['expires_at' => $lot['expires_at'], 'points' => 0];
            } elseif ($lot['expires_at']->gt($byDay[$day]['expires_at'])) {
                $byDay[$day]['expires_at'] = $lot['expires_at'];
            }
            $byDay[$day]['points'] += $lot['points'];
        }
        ksort($byDay);

        $lots = [];
        $cumOld = 0;
        $cumNew = 0;
        foreach ($byDay as $lot) {
            $cumOld += $lot['points'];
            $points = intdiv($cumOld, $factor) - $cumNew;
            if ($points > 0) {
                $lots[] = ['expires_at' => $lot['expires_at'], 'points' => $points];
                $cumNew += $points;
            }
        }

        return [
            'old'        => $old,
            'new'        => $new,
            'lots'       => $lots,
            'residue'    => max(0, $new - $cumNew),
            'ledger_sum' => $ledgerSum,
        ];
    }

    /** Aggregate wallet plan (reads only). */
    public function planWallets(int $factor): array
    {
        $summary = $this->emptyWalletSummary();
        LoyaltyWallet::query()->chunkById(200, function ($wallets) use ($factor, &$summary) {
            foreach ($wallets as $wallet) {
                $this->addToSummary($summary, $wallet, $factor, null);
            }
        });

        return $summary;
    }

    /**
     * Do it. The caller checks factor >= 2 and maintenance mode; the
     * already-rescaled check is repeated here under a row lock.
     *
     * Order inside the transaction: lock + marker check, then every config
     * value (settings, earn rate, reward prices, QR codes, carts), then the
     * wallets, then the expiry epoch and the markers.
     *
     * @return array{plan: array, wallets: array, changed_wallet_ids: array<int,int>}
     *
     * @throws AlreadyRescaledException
     */
    public function apply(int $factor): array
    {
        // The guard row must exist before the transaction so that two
        // concurrent runs both find (and queue on) the same row.
        Setting::query()->createOrFirst(['key' => self::LOCK_KEY], ['value' => '', 'type' => 'string', 'group' => 'system']);

        $changed = [];

        $result = DB::transaction(function () use ($factor, &$changed) {
            Setting::query()->where('key', self::LOCK_KEY)->lockForUpdate()->first();
            $marker = Setting::query()->where('key', self::MARKER_FACTOR)->lockForUpdate()->first();
            if ($marker !== null) {
                throw new AlreadyRescaledException("Loyalty was already rescaled (factor {$marker->value}).");
            }

            $plan = $this->plan($factor);
            $epoch = (int) LoyaltyTransaction::query()->max('id');

            // 1. Config first, so nothing priced in points stays in the old scale.
            $this->writeSettings($plan);
            $this->writeColumn(RedeemItem::withTrashed(), 'points_cost', $plan['redeem_items']);
            $this->writeColumn(PromoQrCode::query(), 'points', $plan['promo_qr']);
            $this->writeColumn(QrCode::query(), 'points_awarded', $plan['qr_codes']);
            $this->writeColumn(Cart::query(), 'points_used', $plan['carts']);

            // 2. Wallets. Their lots are computed with the OLD ledger window,
            // so the epoch setting is only moved afterwards.
            $summary = $this->emptyWalletSummary();
            LoyaltyWallet::query()->lockForUpdate()->chunkById(500, function ($wallets) use ($factor, &$summary, &$changed) {
                $rows = [];
                foreach ($wallets as $wallet) {
                    $this->addToSummary($summary, $wallet, $factor, function (LoyaltyWallet $wallet, array $w) use ($factor, &$changed, &$rows) {
                        if ($w['old'] === 0) {
                            return; // 0 stays 0; its expiry window is reset by the global epoch
                        }
                        array_push($rows, ...$this->walletRows($wallet, $w, $factor));
                        if ($w['new'] !== $w['old']) {
                            LoyaltyWallet::query()->whereKey($wallet->id)->update(['points' => $w['new']]);
                            $changed[] = $wallet->id;
                        }
                    });
                }
                // Multi-row inserts: ids ascend in row order, so each wallet's
                // close-out row precedes its carried-over rows.
                foreach (array_chunk($rows, 1000) as $batch) {
                    LoyaltyTransaction::query()->insert($batch);
                }
            });

            // 3. Expiry window and markers.
            Setting::set(PointsExpiry::EPOCH_SETTING, $epoch, 'number', 'loyalty');
            Setting::set(self::MARKER_FACTOR, $factor, 'number', 'loyalty');
            Setting::set(self::MARKER_AT, now()->toIso8601String(), 'string', 'loyalty');

            return ['plan' => $plan, 'wallets' => $summary];
        });

        // Refresh Apple/Google wallet passes only once the new balances are committed.
        foreach (array_chunk($changed, 200) as $ids) {
            foreach (LoyaltyWallet::query()->whereKey($ids)->get() as $wallet) {
                \App\Events\LoyaltyWalletUpdated::dispatch($wallet, LoyaltyTransaction::TYPE_RESCALE);
            }
        }

        return $result + ['changed_wallet_ids' => $changed];
    }

    private function writeSettings(array $plan): void
    {
        foreach ($plan['settings'] as $key => $s) {
            if ($s['new'] !== null && ($s['new'] !== $s['old'] || $s['source'] === 'default')) {
                // A code default is pinned too, so a later default change can't flip the scale.
                Setting::set($key, $s['new'], 'number', $s['group']);
            }
        }

        $rate = $plan['earn_rate'];
        if ($rate['new_egp_per_point'] !== null) {
            $value = $rate['new_egp_per_point'];
            if (abs($value - round($value)) < 1e-9) {
                Setting::set('loyalty.egp_per_point', (int) round($value), 'number', 'loyalty');
            } else {
                // 'number' settings read back as int; keep the decimals.
                Setting::set('loyalty.egp_per_point', (string) round($value, 4), 'string', 'loyalty');
            }
        }
    }

    /** Ledger rows for one wallet whose balance changes (close-out row first). */
    private function walletRows(LoyaltyWallet $wallet, array $w, int $factor): array
    {
        $now = now();
        $row = fn (int $points, string $ref, $expiresAt, string $description) => [
            'wallet_id'      => $wallet->id,
            'type'           => LoyaltyTransaction::TYPE_RESCALE,
            'points'         => $points,
            'expires_at'     => $expiresAt,
            'description'    => $description,
            'reference_type' => $ref,
            'reference_id'   => $factor,
            'created_by'     => null,
            'created_at'     => $now,
            'updated_at'     => $now,
        ];

        $rows = [$row(-$w['old'], LoyaltyTransaction::REF_RESCALE_CLOSE, null, "Points rescaled 1:{$factor} ({$w['old']} old points)")];
        if ($w['new'] < 0) {
            $rows[] = $row($w['new'], LoyaltyTransaction::REF_RESCALE_LOT, null, 'Negative balance carried over after rescale');

            return $rows;
        }
        foreach ($w['lots'] as $lot) {
            $rows[] = $row($lot['points'], LoyaltyTransaction::REF_RESCALE_LOT, $lot['expires_at'], 'Points carried over after rescale');
        }
        if ($w['residue'] > 0) {
            $rows[] = $row($w['residue'], LoyaltyTransaction::REF_RESCALE_LOT, null, 'Points carried over after rescale');
        }

        return $rows;
    }

    private function emptyWalletSummary(): array
    {
        return [
            'wallets_total'          => 0,
            'wallets_with_balance'   => 0,
            'wallets_changed'        => 0,
            'wallets_negative'       => 0,
            'ledger_mismatch_ids'    => [],
            'sum_before'             => 0,
            'sum_after'              => 0,
            'expiring_lot_points'    => 0,
            'non_expiring_points'    => 0,
            'rows_to_write'          => 0,
        ];
    }

    private function addToSummary(array &$summary, LoyaltyWallet $wallet, int $factor, ?callable $write): void
    {
        $w = $this->planWallet($wallet, $factor);
        $summary['wallets_total']++;
        $summary['sum_before'] += $w['old'];
        if ($w['ledger_sum'] !== $w['old']) {
            $summary['ledger_mismatch_ids'][] = $wallet->id;
        }
        $summary['sum_after'] += $w['new'];
        if ($w['old'] < 0) {
            $summary['wallets_negative']++;
            $summary['rows_to_write'] += 1 + ($w['new'] !== 0 ? 1 : 0);
        } elseif ($w['old'] > 0) {
            $summary['wallets_with_balance']++;
            $summary['rows_to_write'] += 1 + count($w['lots']) + ($w['residue'] > 0 ? 1 : 0);
            $summary['expiring_lot_points'] += array_sum(array_column($w['lots'], 'points'));
            $summary['non_expiring_points'] += $w['residue'];
        }
        if ($w['new'] !== $w['old']) {
            $summary['wallets_changed']++;
        }
        if ($write) {
            $write($wallet, $w);
        }
    }

    private function planEarnRate(int $factor): array
    {
        $egpPerPoint = $this->earning->egpPerPoint();
        if ($egpPerPoint !== null) {
            return [
                'source'            => 'loyalty.egp_per_point',
                'old_egp_per_point' => $egpPerPoint,
                'new_egp_per_point' => $egpPerPoint * $factor,
            ];
        }

        $pointsPerEgp = (int) Setting::get(
            'loyalty.points_per_order_egp',
            (int) config('core.loyalty.points_per_order_egp', 1),
        );
        if ($pointsPerEgp <= 0) {
            return ['source' => 'loyalty.points_per_order_egp=' . $pointsPerEgp . ' (earning off)', 'old_egp_per_point' => null, 'new_egp_per_point' => null];
        }

        return [
            'source'            => "loyalty.points_per_order_egp={$pointsPerEgp} (legacy, round)",
            'old_egp_per_point' => 1 / $pointsPerEgp,
            'new_egp_per_point' => $factor / $pointsPerEgp,
        ];
    }

    private function planSettings(int $factor): array
    {
        $out = [];
        foreach (self::pointSettings() as $key => [$default, $group]) {
            $row = Setting::where('key', $key)->first();
            $raw = $row ? $row->value : $default;
            if (! is_numeric($raw)) {
                $out[$key] = ['old' => $raw, 'new' => null, 'source' => 'db (not numeric, skipped)', 'group' => $group];
                continue;
            }
            $old = (int) $raw;
            $out[$key] = ['old' => $old, 'new' => self::ceilDiv($old, $factor), 'source' => $row ? 'db' : 'default', 'group' => $group];
        }

        // Points per 1 EGP when paying with points: divide, rounding DOWN
        // (a point is then worth at least what it was), never below 1.
        $row = Setting::where('key', 'loyalty.points_to_egp_rate')->first();
        $old = (int) ($row ? $row->value : 10);
        $new = max(1, intdiv(max(0, $old), $factor));
        $out['loyalty.points_to_egp_rate'] = [
            'old'    => $old,
            'new'    => $new,
            'source' => $row ? 'db' : 'default',
            'group'  => 'loyalty',
            'note'   => $old % $factor === 0 && $old >= $factor ? null : 'not an exact multiple of the factor: rounded down (min 1)',
        ];

        return $out;
    }

    /** id => [old, new, extra...] for rows with a positive value in $column. */
    private function planColumn($query, string $column, int $factor, array $extra): array
    {
        $rows = [];
        $query->where($column, '>', 0)->orderBy('id')
            ->get(array_merge(['id', $column], $extra))
            ->each(function ($row) use (&$rows, $column, $factor, $extra) {
                $old = (int) $row->getRawOriginal($column);
                $entry = ['old' => $old, 'new' => self::ceilDiv($old, $factor)];
                foreach ($extra as $e) {
                    $entry[$e] = $row->getRawOriginal($e);
                }
                $rows[$row->id] = $entry;
            });

        return $rows;
    }

    private function writeColumn($query, string $column, array $rows): void
    {
        foreach ($rows as $id => $row) {
            if ($row['new'] !== $row['old']) {
                (clone $query)->whereKey($id)->update([$column => $row['new']]);
            }
        }
    }
}
