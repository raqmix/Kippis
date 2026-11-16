<?php

namespace App\Services\Loyalty;

use App\Core\Models\Customer;
use App\Core\Models\CustomerRedeemWallet;
use App\Core\Models\Order;
use App\Core\Models\Setting;
use App\Events\OrderStreakAlmostComplete;
use App\Events\OrderStreakRewardIssued;
use App\Services\Rewards\RewardIssuer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Kippis Streak (order based; the daily check-in streak is separate).
 *
 * A "day" is a Cairo calendar day on which the customer had an app order
 * complete (dated by the order's created_at). On completion: same day as
 * the last counted one -> unchanged, the day after -> +1, anything else
 * -> 1. Reaching `order_streak.length` (default 5) issues a free coffee
 * (source streak) and resets the count to 0.
 *
 * Settings: order_streak.enabled (default true), order_streak.length (5).
 */
class OrderStreakService
{
    public function __construct(private RewardIssuer $issuer) {}

    public function enabled(): bool
    {
        return (bool) Setting::get('order_streak.enabled', true) && $this->length() > 0;
    }

    public function length(): int
    {
        return max(0, (int) Setting::get('order_streak.length', 5));
    }

    public static function businessDate(\DateTimeInterface $at): CarbonImmutable
    {
        return CarbonImmutable::instance($at)
            ->setTimezone(config('core.business_timezone', 'Africa/Cairo'))
            ->startOfDay();
    }

    /**
     * Count one completed app order. Returns the issued reward when this
     * order finished a streak, otherwise null.
     */
    public function recordCompletedOrder(Order $order): ?CustomerRedeemWallet
    {
        if (! $this->enabled() || ! $order->customer_id) {
            return null;
        }

        $length = $this->length();
        $day = self::businessDate($order->created_at ?? now());
        $events = [];
        $reward = null;

        DB::transaction(function () use ($order, $length, $day, &$events, &$reward) {
            /** @var Customer|null $customer */
            $customer = Customer::query()->whereKey($order->customer_id)->lockForUpdate()->first();
            if (! $customer) {
                return;
            }

            $last = $customer->order_streak_last_date
                ? CarbonImmutable::parse($customer->order_streak_last_date->toDateString(), $day->timezone)->startOfDay()
                : null;

            if ($last && $day->lessThanOrEqualTo($last)) {
                return; // same day (or an older order completing late): nothing to count
            }

            $count = ($last && $last->addDay()->equalTo($day))
                ? (int) $customer->order_streak_count + 1
                : 1;

            if ($count >= $length) {
                $reward = $this->issuer->issueFreeItemFromSettings(
                    $customer, 'streak', 'free_coffee', 'streak:' . $day->toDateString(),
                );
                $count = 0;
                $events[] = new OrderStreakRewardIssued($customer, $reward);
            } elseif ($count === $length - 1) {
                $events[] = new OrderStreakAlmostComplete($customer, $count, $length);
            }

            $customer->forceFill([
                'order_streak_count'     => $count,
                'order_streak_last_date' => $day->toDateString(),
            ])->save();
        });

        foreach ($events as $event) {
            event($event);
        }

        return $reward;
    }

    /**
     * Shape of GET /v1/order-streak. The shown count drops to 0 once a
     * full Cairo day was missed (last order before yesterday).
     *
     * @return array{enabled:bool,count:int,length:int,remaining:int,ordered_today:bool,at_risk:bool}
     */
    public function status(Customer $customer): array
    {
        $length = $this->length();
        $today = self::businessDate(now());
        $last = $customer->order_streak_last_date
            ? CarbonImmutable::parse($customer->order_streak_last_date->toDateString(), $today->timezone)->startOfDay()
            : null;

        $orderedToday = $last !== null && $last->equalTo($today);
        $alive = $last !== null && $last->greaterThanOrEqualTo($today->subDay());
        $count = $alive ? (int) $customer->order_streak_count : 0;

        return [
            'enabled'       => $this->enabled(),
            'count'         => $count,
            'length'        => $length,
            'remaining'     => max(0, $length - $count),
            'ordered_today' => $orderedToday,
            // Streak is running but today's order is still missing: it breaks at midnight.
            'at_risk'       => $count > 0 && ! $orderedToday,
        ];
    }
}
