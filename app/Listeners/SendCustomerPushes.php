<?php

namespace App\Listeners;

use App\Events\BirthdayTreatIssued;
use App\Events\CupCardCompleted;
use App\Events\CupCardStamped;
use App\Events\MonthlyDrawEntered;
use App\Events\OrderStreakAlmostComplete;
use App\Events\OrderStreakRewardIssued;
use App\Events\ReferralRewardIssued;
use App\Services\Notifications\CustomerPushService;
use App\Services\Notifications\PushText;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Event-driven customer pushes (brief section E). Auto-discovered from the
 * handle* method signatures: do NOT also register in EventServiceProvider
 * (would double-fire).
 */
class SendCustomerPushes implements ShouldQueue
{
    public function __construct(private readonly CustomerPushService $pushes)
    {
    }

    /** One push per order (dedup key). A 0% next step is just a stamp: nothing worth a push. */
    public function handleCupCardStamped(CupCardStamped $event): void
    {
        $percent = (int) round($event->nextPercent);
        if ($percent <= 0) {
            return;
        }

        $this->pushes->sendText(
            $event->customer,
            'cup_card_stamped',
            PushText::title('pushes.cup_card_stamped.title'),
            $percent >= 100
                ? PushText::body('pushes.cup_card_stamped.body_free')
                : PushText::body('pushes.cup_card_stamped.body', ['percent' => $percent]),
            ['stamps' => $event->stamps, 'next_percent' => $percent],
            null,
            'cup_card_stamped:' . ($event->orderId ?? $event->stamps . ':' . now()->timestamp),
        );
    }

    public function handleCupCardCompleted(CupCardCompleted $event): void
    {
        $this->pushes->send(
            $event->customer,
            'cup_card_completed',
            [],
            [],
            'cup_card_completed:' . ($event->orderId ?? now()->timestamp),
        );
    }

    public function handleStreakAlmost(OrderStreakAlmostComplete $event): void
    {
        $this->pushes->send(
            $event->customer,
            'streak_almost',
            ['n' => $event->count],
            ['count' => $event->count, 'length' => $event->length],
            'streak_almost:' . $this->cairoNow()->format('Y-m-d'),
        );
    }

    public function handleStreakReward(OrderStreakRewardIssued $event): void
    {
        $this->pushes->send(
            $event->customer,
            'streak_reward',
            [],
            ['reward_id' => $event->reward->id],
            'streak_reward:' . $event->reward->id,
        );
    }

    /** Fires on every completed app order; the push goes out once per Cairo month. */
    public function handleBigWinEntry(MonthlyDrawEntered $event): void
    {
        $this->pushes->send(
            $event->customer,
            'big_win_entry',
            [],
            [],
            'big_win_entry:' . $this->cairoNow()->format('Y-m'),
        );
    }

    public function handleReferralReward(ReferralRewardIssued $event): void
    {
        $key = "referral_reward:{$event->inviter->id}:{$event->invitee->id}";
        foreach ([$event->inviter, $event->invitee] as $customer) {
            $this->pushes->send($customer, 'referral_reward', [], [], $key);
        }
    }

    public function handleBirthday(BirthdayTreatIssued $event): void
    {
        $this->pushes->send(
            $event->customer,
            'birthday',
            [],
            ['reward_id' => $event->reward->id],
            'birthday:' . ($event->reward->source_ref ?: $event->reward->id),
        );
    }

    private function cairoNow(): CarbonImmutable
    {
        return CarbonImmutable::now(config('core.business_timezone', 'Africa/Cairo'));
    }
}
