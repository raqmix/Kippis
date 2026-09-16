<?php

namespace App\Jobs;

use App\Core\Models\ScheduledPush;
use App\Core\Models\ScheduledPushSend;
use App\Core\Services\FcmService;
use App\Services\ScheduledPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * One chunk of the fan-out. ScheduledPushService::fireOne dispatches one
 * of these per 500-customer chunk; the job sends the FCM push to each
 * token in turn and increments the send-log counters.
 *
 * Kept per-chunk (not per-customer) so the queue doesn't drown in 10,000
 * tiny jobs, but still small enough that a single failure only loses one
 * chunk on the retry. FCM v1 has no true multicast — we do serial POSTs
 * inside the job, which is fine at Kippis' scale.
 */
class SendScheduledPushBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public int $scheduledPushId,
        public int $scheduledPushSendId,
        public array $tokens,
    ) {}

    public function handle(
        FcmService $fcm,
        ScheduledPushService $service,
    ): void {
        $push = ScheduledPush::find($this->scheduledPushId);
        $send = ScheduledPushSend::find($this->scheduledPushSendId);

        // Row deleted between claim and job execution (soft delete or
        // outright drop) — silently drop this batch.
        if (! $push || ! $send) {
            Log::warning('scheduled_push.batch.skipped_missing', [
                'push_id' => $this->scheduledPushId,
                'send_id' => $this->scheduledPushSendId,
            ]);
            return;
        }

        $title = $service->bilingualTitle($push);
        $body = $service->bilingualBody($push);
        $data = $service->dataPayload($push);

        $ok = 0;
        $failed = 0;
        foreach ($this->tokens as $token) {
            try {
                $fcm->sendDataToToken($token, $title, $body, $data);
                $ok++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('scheduled_push.token.failed', [
                    'push_id' => $push->id,
                    'error' => $e->getMessage(),
                    // deliberately don't log token — it's PII in this context
                ]);
            }
        }

        // Increment atomically — multiple batches for the same send row
        // may finish concurrently.
        $send->increment('sent_ok_count', $ok);
        $send->increment('sent_failed_count', $failed);
    }
}
