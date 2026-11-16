<?php

namespace App\Console\Commands;

use App\Core\Models\Customer;
use App\Core\Models\CustomerPushLog;
use App\Core\Models\CustomerRedeemWallet;
use App\Core\Models\LoyaltyTransaction;
use App\Core\Models\LoyaltyWallet;
use App\Core\Models\RedeemItem;
use App\Core\Models\Setting;
use App\Services\Loyalty\PointsExpiry;
use App\Services\Notifications\CustomerPushService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Daily lifecycle pushes (Cairo ~11:00, see routes/console.php). All four are
 * marketing pushes, so the opt-in check and the 1-per-Cairo-day cap in
 * CustomerPushService apply; when a customer qualifies for several, the
 * order below decides which one wins the day.
 *
 *   gift_expiring           wallet gift whose expires_at is on the Cairo day 3 days from now
 *                           (dedup: gift_expiring:{wallet entry id})
 *   points_expiring         earned points that expire within 14 days (FIFO, same maths
 *                           as loyalty:expire-points)  (dedup: once per Cairo month)
 *   enough_points_for_gift  balance >= the cheapest active redeem item; once per item per
 *                           crossing, where a crossing ends at the next points deduction
 *                           (dedup: item id + last deduction transaction id)
 *   close_to_gift           within 10 points of the next redeem item (dedup: item + ISO week)
 */
class PushesLifecycleTriggersCommand extends Command
{
    protected $signature = 'pushes:lifecycle-triggers
        {--only= : Comma-separated subset: gift_expiring,points_expiring,enough_points_for_gift,close_to_gift}';

    protected $description = 'Send the daily lifecycle pushes (gift/points expiring, enough/close to a gift).';

    private const TRIGGERS = ['gift_expiring', 'points_expiring', 'enough_points_for_gift', 'close_to_gift'];
    private const CLOSE_TO_GIFT_POINTS = 10;
    private const POINTS_EXPIRING_DAYS = 14;
    private const CHUNK = 500;

    /** @var array<string,array<string,int>> trigger => outcome => count */
    private array $stats = [];

    private CarbonImmutable $cairoNow;

    public function __construct(
        private readonly CustomerPushService $pushes,
        private readonly PointsExpiry $expiry,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->cairoNow = CarbonImmutable::now(config('core.business_timezone', 'Africa/Cairo'));

        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));
        $run = $only ? array_values(array_intersect(self::TRIGGERS, $only)) : self::TRIGGERS;

        foreach ($run as $trigger) {
            $this->{'run' . str_replace('_', '', ucwords($trigger, '_'))}();
        }

        foreach ($this->stats as $trigger => $counts) {
            $this->line($trigger . ': ' . json_encode($counts));
        }

        return self::SUCCESS;
    }

    private function runGiftExpiring(): void
    {
        $start = $this->cairoNow->addDays(3)->startOfDay()->utc();
        $end = $this->cairoNow->addDays(4)->startOfDay()->utc();
        $seen = [];

        CustomerRedeemWallet::query()
            ->where('status', CustomerRedeemWallet::STATUS_AVAILABLE)
            ->where('expires_at', '>=', $start)
            ->where('expires_at', '<', $end)
            ->whereHas('customer', fn (Builder $c) => $this->eligible($c))
            ->with('customer')
            ->chunkById(self::CHUNK, function ($gifts) use (&$seen) {
                $pairs = [];
                foreach ($gifts as $gift) {
                    // one push per customer even with several gifts expiring that day
                    if (! isset($seen[$gift->customer_id])) {
                        $seen[$gift->customer_id] = true;
                        $pairs[$gift->customer_id] = ["gift_expiring:{$gift->id}", $gift];
                    }
                }
                $sent = $this->alreadySent($pairs);
                foreach ($pairs as $customerId => [$key, $gift]) {
                    if (isset($sent[$customerId])) {
                        continue;
                    }
                    $this->dispatchPush('gift_expiring', $gift->customer, [], ['reward_id' => $gift->id], $key);
                }
            });
    }

    private function runPointsExpiring(): void
    {
        $now = $this->cairoNow->utc();
        $cutoff = $now->addDays(self::POINTS_EXPIRING_DAYS);
        $key = 'points_expiring:' . $this->cairoNow->format('Y-m');

        LoyaltyWallet::query()
            ->where('points', '>', 0)
            ->whereHas('customer', fn (Builder $c) => $this->eligible($c))
            ->whereHas('transactions', fn (Builder $t) => $t
                ->where('type', LoyaltyTransaction::TYPE_EARNED)
                ->where('expires_at', '>', $now)
                ->where('expires_at', '<=', $cutoff))
            ->with('customer')
            ->chunkById(self::CHUNK, function ($wallets) use ($cutoff, $key) {
                $pairs = [];
                foreach ($wallets as $wallet) {
                    $pairs[$wallet->customer_id] = [$key, $wallet];
                }
                $sent = $this->alreadySent($pairs);
                foreach ($pairs as $customerId => [$k, $wallet]) {
                    if (isset($sent[$customerId]) || $this->expiry->expiringBy($wallet, $cutoff) <= 0) {
                        continue;
                    }
                    $this->dispatchPush('points_expiring', $wallet->customer, [], [], $k);
                }
            });
    }

    private function runEnoughPointsForGift(): void
    {
        $items = $this->activeItems();
        $cheapest = $items->first();
        if (! $cheapest) {
            return;
        }

        $this->walletsQuery()
            ->where('points', '>=', $cheapest->points_cost)
            ->chunkById(self::CHUNK, function ($wallets) use ($cheapest) {
                $pairs = [];
                foreach ($wallets as $wallet) {
                    // a crossing is identified by the last points deduction: the balance
                    // can only fall below the threshold again through a negative transaction
                    $key = "enough_points:{$cheapest->id}:" . ($wallet->last_deduction_id ?? 0);
                    $pairs[$wallet->customer_id] = [$key, $wallet];
                }
                $sent = $this->alreadySent($pairs);
                foreach ($pairs as $customerId => [$key, $wallet]) {
                    if (isset($sent[$customerId])) {
                        continue;
                    }
                    $this->dispatchPush(
                        'enough_points_for_gift',
                        $wallet->customer,
                        ['gift' => $this->itemNames($cheapest)],
                        ['redeem_item_id' => $cheapest->id],
                        $key,
                    );
                }
            });
    }

    private function runCloseToGift(): void
    {
        $items = $this->activeItems();
        if ($items->isEmpty()) {
            return;
        }
        $week = $this->cairoNow->format('o-\WW');

        $query = $this->walletsQuery()->where(function (Builder $q) use ($items) {
            foreach ($items as $item) {
                $q->orWhereBetween('points', [max(0, $item->points_cost - self::CLOSE_TO_GIFT_POINTS), $item->points_cost - 1]);
            }
        });

        $query->chunkById(self::CHUNK, function ($wallets) use ($items, $week) {
            $pairs = [];
            foreach ($wallets as $wallet) {
                // next item = the cheapest one the customer cannot afford yet
                $next = $items->first(fn ($i) => $i->points_cost > $wallet->points);
                if (! $next || $next->points_cost - $wallet->points > self::CLOSE_TO_GIFT_POINTS) {
                    continue;
                }
                $pairs[$wallet->customer_id] = ["close_to_gift:{$next->id}:{$week}", $wallet, $next];
            }
            $sent = $this->alreadySent(array_map(fn ($p) => [$p[0], $p[1]], $pairs));
            foreach ($pairs as $customerId => [$key, $wallet, $next]) {
                if (isset($sent[$customerId])) {
                    continue;
                }
                $this->dispatchPush(
                    'close_to_gift',
                    $wallet->customer,
                    ['n' => $next->points_cost - $wallet->points, 'gift' => $this->itemNames($next)],
                    ['redeem_item_id' => $next->id],
                    $key,
                );
            }
        });
    }

    /** Wallets of push-eligible customers, with the id of their last points deduction. */
    private function walletsQuery(): Builder
    {
        if (! (bool) Setting::get('loyalty.redemption_enabled', true)) {
            return LoyaltyWallet::query()->whereRaw('1 = 0');
        }

        return LoyaltyWallet::query()
            ->select('loyalty_wallets.*')
            ->selectSub(
                LoyaltyTransaction::query()->select('id')
                    ->whereColumn('wallet_id', 'loyalty_wallets.id')
                    ->where('points', '<', 0)
                    ->orderByDesc('id')->limit(1),
                'last_deduction_id',
            )
            ->whereHas('customer', fn (Builder $c) => $this->eligible($c))
            ->with('customer');
    }

    private function activeItems()
    {
        if (! (bool) Setting::get('loyalty.redemption_enabled', true)) {
            return collect();
        }

        return RedeemItem::query()->where('is_active', true)->where('points_cost', '>', 0)
            ->orderBy('points_cost')->orderBy('id')->get();
    }

    private function itemNames(RedeemItem $item): array
    {
        return ['en' => $item->getTitle('en'), 'ar' => $item->getTitle('ar')];
    }

    /** Marketing needs an opt-in and a token; filter here so we do not log millions of skips. */
    private function eligible(Builder $customers): Builder
    {
        return $customers->where('push_marketing_opt_in', true)->whereNotNull('fcm_token');
    }

    /**
     * Customers in this chunk whose dedup key was already used (one query per
     * chunk), so a daily run does not add a "duplicate" skip row per customer
     * for as long as the condition stays true.
     *
     * @param array<int, array{0:string}> $pairs customer id => [key, ...]
     * @return array<int,true>
     */
    private function alreadySent(array $pairs): array
    {
        if (! $pairs) {
            return [];
        }
        $keys = array_values(array_unique(array_map(fn ($p) => $p[0], $pairs)));
        $done = [];
        CustomerPushLog::query()
            ->whereIn('customer_id', array_keys($pairs))
            ->whereIn('dedup_key', $keys)
            ->get(['customer_id', 'dedup_key'])
            ->each(function ($row) use ($pairs, &$done) {
                if (($pairs[$row->customer_id][0] ?? null) === $row->dedup_key) {
                    $done[$row->customer_id] = true;
                }
            });

        return $done;
    }

    private function dispatchPush(string $trigger, Customer $customer, array $replace, array $data, string $key): void
    {
        $row = $this->pushes->send($customer, $trigger, $replace, $data, $key);
        $outcome = $row->status === CustomerPushLog::STATUS_SKIPPED ? "skipped_{$row->reason}" : $row->status;
        $this->stats[$trigger][$outcome] = ($this->stats[$trigger][$outcome] ?? 0) + 1;
    }
}
