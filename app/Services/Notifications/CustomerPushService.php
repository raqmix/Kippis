<?php

namespace App\Services\Notifications;

use App\Core\Models\Customer;
use App\Core\Models\CustomerPushLog;
use App\Core\Services\FcmService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Single entry point for customer pushes (brief section E).
 *
 *  - transactional: always sent when the customer has an FCM token.
 *  - marketing: at most ONE marketing push per Cairo day (any marketing
 *    type counts). Automatic pushes go to everyone with a token unless the
 *    setting pushes.marketing_requires_opt_in is on; admin scheduled pushes
 *    pass their own audience rule.
 *  - dedup key: a key can be consumed once per customer.
 *
 * Every attempt is written to customer_push_log, skips included.
 */
class CustomerPushService
{
    public const MARKETING = 'marketing';
    public const TRANSACTIONAL = 'transactional';

    /** type => [category, app screen the tap routes to (null = none)] */
    public const TYPES = [
        'gift_expiring' => [self::MARKETING, 'my_rewards'],
        'birthday' => [self::MARKETING, 'my_rewards'],
        'referral_reward' => [self::TRANSACTIONAL, 'my_rewards'],
        'streak_reward' => [self::TRANSACTIONAL, 'my_rewards'],
        'points_expiring' => [self::MARKETING, 'loyalty'],
        'enough_points_for_gift' => [self::MARKETING, 'loyalty'],
        'close_to_gift' => [self::MARKETING, 'loyalty'],
        'big_win_entry' => [self::MARKETING, 'loyalty'],
        'streak_almost' => [self::MARKETING, 'streak'],
        'scheduled_push' => [self::MARKETING, null],
    ];

    public function __construct(private readonly FcmService $fcm)
    {
    }

    /**
     * Send a push whose text lives in lang/{en,ar}/pushes.php under `pushes.{type}`.
     *
     * @param array $replace  lang placeholders (scalar or per-locale array)
     * @param array $data     extra data payload; `type` and `screen` are added
     */
    public function send(
        Customer $customer,
        string $type,
        array $replace = [],
        array $data = [],
        ?string $dedupKey = null,
        ?string $category = null,
    ): CustomerPushLog {
        return $this->sendText(
            $customer,
            $type,
            PushText::title("pushes.{$type}.title", $replace),
            PushText::body("pushes.{$type}.body", $replace),
            $data,
            $category,
            $dedupKey,
        );
    }

    /**
     * Send already-built text (used by admin scheduled pushes).
     *
     * @param bool $enforceOptIn marketing needs the opt-in flag; admin
     *        scheduled pushes with audience "all" pass false to keep that
     *        audience's meaning (everyone with a token). The daily cap
     *        always applies.
     */
    public function sendText(
        Customer $customer,
        string $type,
        string $title,
        string $body,
        array $data = [],
        ?string $category = null,
        ?string $dedupKey = null,
        ?bool $enforceOptIn = null,
    ): CustomerPushLog {
        $enforceOptIn ??= self::marketingRequiresOptIn();
        [$defaultCategory, $screen] = self::TYPES[$type] ?? [self::MARKETING, null];
        $category ??= $defaultCategory;

        $payload = ['type' => $type];
        if ($screen !== null) {
            $payload['screen'] = $screen;
        }
        $payload = array_merge($payload, $data);

        if (! $customer->fcm_token) {
            return $this->logSkip($customer, $type, $category, 'no_token', $dedupKey);
        }

        if ($category === self::MARKETING && $enforceOptIn && ! $customer->push_marketing_opt_in) {
            return $this->logSkip($customer, $type, $category, 'not_opted_in', $dedupKey);
        }

        // Reserve the slot (dedup key + daily cap) under a per-customer row
        // lock so two concurrent sends cannot both pass the checks. The FCM
        // call happens after the lock is released.
        $reserved = DB::transaction(function () use ($customer, $type, $category, $dedupKey) {
            Customer::query()->whereKey($customer->getKey())->lockForUpdate()->value('id');

            if ($dedupKey !== null && CustomerPushLog::query()
                ->where('customer_id', $customer->id)->where('dedup_key', $dedupKey)->exists()) {
                return 'duplicate';
            }

            if ($category === self::MARKETING && $this->marketingSentToday($customer)) {
                return 'daily_cap';
            }

            return CustomerPushLog::create([
                'customer_id' => $customer->id,
                'type' => $type,
                'category' => $category,
                'status' => CustomerPushLog::STATUS_SENT,
                'dedup_key' => $dedupKey,
                'attempt_key' => $dedupKey,
                'sent_at' => now(),
            ]);
        });

        if (is_string($reserved)) {
            return $this->logSkip($customer, $type, $category, $reserved, $dedupKey);
        }

        try {
            if ($this->fcm->sendDataToToken($customer->fcm_token, $title, $body, $payload) === false) {
                throw new \RuntimeException('fcm_rejected');
            }
        } catch (\Throwable $e) {
            // Free the key and the cap so a later run can retry.
            $reserved->update([
                'status' => CustomerPushLog::STATUS_FAILED,
                'reason' => mb_substr($e->getMessage(), 0, 120),
                'dedup_key' => null,
            ]);
            Log::warning('customer_push.failed', [
                'customer_id' => $customer->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }

        return $reserved;
    }

    public function marketingSentToday(Customer $customer): bool
    {
        [$start, $end] = self::businessDayBounds();

        return CustomerPushLog::query()
            ->where('customer_id', $customer->id)
            ->where('category', self::MARKETING)
            ->where('status', CustomerPushLog::STATUS_SENT)
            ->where('sent_at', '>=', $start)
            ->where('sent_at', '<', $end)
            ->exists();
    }

    /** [start, end) of the current Cairo day, as UTC instants. */
    public static function businessDayBounds(): array
    {
        $tz = config('core.business_timezone', 'Africa/Cairo');
        $start = CarbonImmutable::now($tz)->startOfDay();

        return [$start->utc(), $start->addDay()->startOfDay()->utc()];
    }

    private function logSkip(Customer $customer, string $type, string $category, string $reason, ?string $key): CustomerPushLog
    {
        return CustomerPushLog::create([
            'customer_id' => $customer->id,
            'type' => $type,
            'category' => $category,
            'status' => CustomerPushLog::STATUS_SKIPPED,
            'reason' => $reason,
            'attempt_key' => $key,
            'sent_at' => now(),
        ]);
    }

    /** Automatic marketing pushes need the opt-in flag only when this setting is on (default off). */
    public static function marketingRequiresOptIn(): bool
    {
        return (bool) \App\Core\Models\Setting::get('pushes.marketing_requires_opt_in', false);
    }
}
