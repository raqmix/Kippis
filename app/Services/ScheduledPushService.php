<?php

namespace App\Services;

use App\Core\Models\Customer;
use App\Core\Models\ScheduledPush;
use App\Core\Models\ScheduledPushSend;
use App\Core\Services\FcmService;
use App\Jobs\SendScheduledPushBatchJob;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScheduledPushService
{
    public function __construct(
        private readonly FcmService $fcm,
    ) {}

    /**
     * Called every 5 minutes by FireDueScheduledPushesCommand. For every
     * active push whose window contains `now`, tries to claim the window
     * (via the unique index on scheduled_push_sends) and fires it.
     *
     * Returns [ScheduledPush $push, int $recipients_count][] for the ones
     * that actually fired this tick — used by the command's exit summary.
     */
    public function fireAllDueNow(CarbonImmutable $now): array
    {
        $fired = [];

        $candidates = ScheduledPush::active()
            ->where(function (Builder $q) use ($now) {
                // Prefilter: only rows whose day_of_week matches today or
                // is null. This is a cheap index-friendly filter; the
                // in-window check is done per row below because it depends
                // on the row's timezone.
                $q->whereNull('day_of_week');
                foreach ($this->possibleDayOfWeekMatches($now) as $dow) {
                    $q->orWhere('day_of_week', $dow);
                }
            })
            ->get();

        foreach ($candidates as $push) {
            if (! $push->isInFireWindow($now)) {
                continue;
            }
            $result = $this->fireOne($push, $now);
            if ($result !== null) {
                $fired[] = [$push, $result];
            }
        }

        return $fired;
    }

    /**
     * Claim the current window for `$push` and dispatch the send batch(es).
     * Idempotent — a losing racer returns null. Returns the recipient
     * count when we win the claim.
     */
    public function fireOne(ScheduledPush $push, CarbonImmutable $now): ?int
    {
        $window = $push->targetWindowFor($now);

        // Claim the window. The unique index on (scheduled_push_id,
        // window_at) makes this a hard dedup — two concurrent cron runs
        // can call this and only one wins.
        try {
            $send = ScheduledPushSend::create([
                'scheduled_push_id' => $push->id,
                'window_at' => $window,
                'fired_at' => $now,
                'recipients_count' => 0,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Duplicate key — someone else already claimed this window.
            if (str_contains($e->getMessage(), 'Duplicate') || (int) $e->getCode() === 23000) {
                return null;
            }
            throw $e;
        }

        $recipientsQuery = $this->audienceQuery($push);
        $count = 0;

        // Chunk to keep memory bounded and to let queued jobs retry
        // independently. Each chunk becomes one queued batch job.
        $recipientsQuery->chunkById(500, function ($chunk) use ($push, $send, &$count) {
            $tokens = $chunk->pluck('fcm_token')->filter()->values()->all();
            if (empty($tokens)) {
                return;
            }
            SendScheduledPushBatchJob::dispatch(
                $push->id,
                $send->id,
                $tokens,
            );
            $count += count($tokens);
        });

        $send->update(['recipients_count' => $count]);
        $push->update([
            'last_sent_at' => $now,
            'last_sent_count' => $count,
        ]);

        Log::info('scheduled_push.fired', [
            'push_id' => $push->id,
            'name' => $push->name,
            'window_at' => $window->toIso8601String(),
            'recipients' => $count,
        ]);

        return $count;
    }

    /**
     * Send this push to a single customer immediately, bypassing all
     * window / dedup / audience logic. Used by the Filament "Test send"
     * action. Does NOT touch last_sent_at or the send log.
     */
    public function testSendToCustomer(ScheduledPush $push, Customer $customer): void
    {
        if (! $customer->fcm_token) {
            throw new \DomainException('Customer has no FCM token registered.');
        }

        $this->fcm->sendDataToToken(
            $customer->fcm_token,
            $this->bilingualTitle($push),
            $this->bilingualBody($push),
            $this->dataPayload($push),
        );
    }

    /**
     * Preview the audience count for a given push without firing. Used
     * by the Filament form's live audience-size badge.
     */
    public function audienceSize(ScheduledPush $push): int
    {
        return $this->audienceQuery($push)->count();
    }

    /**
     * Build the query resolving `push.audience` into customers.
     * Callers should chunk on this — could be tens of thousands of rows.
     */
    public function audienceQuery(ScheduledPush $push): Builder
    {
        $q = Customer::query()->whereNotNull('fcm_token');

        return match ($push->audience) {
            'all' => $q,
            'opted_in_only' => $q->where('push_marketing_opt_in', true),
            'has_ordered' => $q->where('push_marketing_opt_in', true)
                ->whereHas('orders', fn (Builder $o) => $o->where('status', 'completed')),
            'inactive_30d' => $q->where('push_marketing_opt_in', true)
                ->whereDoesntHave('orders', fn (Builder $o) => $o->where('status', 'completed')
                    ->where('created_at', '>=', now()->subDays(30))),
            default => $q->where('push_marketing_opt_in', true),
        };
    }

    /**
     * Days-of-week we might match against on this tick. Almost always
     * just today; on the daily-row edge case where "now" is inside the
     * grace window right after midnight, we might still be trying to
     * fire yesterday's window — but the model's targetWindowFor handles
     * that correctly, so the prefilter is safe with just today.
     */
    private function possibleDayOfWeekMatches(CarbonImmutable $now): array
    {
        // We use UTC here as a coarse prefilter; per-row timezone check
        // happens in isInFireWindow. To avoid missing a row whose Cairo
        // day is different from UTC day near midnight, include yesterday
        // and today.
        return [
            (int) $now->dayOfWeek,
            (int) $now->subDay()->dayOfWeek,
        ];
    }

    public function bilingualTitle(ScheduledPush $push): string
    {
        // Concatenate EN + AR — matches the pattern used by
        // NotifyCustomerOnOrderStatusUpdate, since we have no per-customer
        // language preference to key off of.
        return sprintf('%s • %s', $push->title_en, $push->title_ar);
    }

    public function bilingualBody(ScheduledPush $push): string
    {
        return sprintf('%s • %s', $push->body_en, $push->body_ar);
    }

    public function dataPayload(ScheduledPush $push): array
    {
        return [
            'type' => 'scheduled_push',
            'push_id' => $push->id,
            'deeplink_type' => $push->deeplink_type,
            'deeplink_id' => $push->deeplink_id,
        ];
    }
}
