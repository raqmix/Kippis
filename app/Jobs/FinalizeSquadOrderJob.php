<?php

namespace App\Jobs;

use App\Core\Models\SquadSession;
use App\Services\SquadOrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs at the squad's payment deadline. Wraps the finalizer which
 * filters the items_snapshot to paid members and creates the Order
 * (which then dispatches PushOrderToFoodics via OrderCreated).
 *
 * Idempotent — the finalizer itself acquires a Cache::lock per session
 * and exits early if the session is already terminal. Host's manual
 * "Push now" tap can race this safely.
 */
class FinalizeSquadOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public array $backoff = [10];

    public function __construct(public int $squadSessionId)
    {
    }

    public function handle(SquadOrderService $service): void
    {
        $session = SquadSession::find($this->squadSessionId);
        if (! $session) {
            Log::info('SQUAD_FINALIZE_SKIPPED_MISSING', ['squad_session_id' => $this->squadSessionId]);
            return;
        }

        if (! $session->isAwaitingPayments()) {
            // Already finalized, cancelled, or never entered the split-pay
            // window. Either the host pushed-now already, or someone
            // cancelled. Idempotent exit — no work to do.
            Log::info('SQUAD_FINALIZE_SKIPPED_TERMINAL', [
                'squad_session_id' => $session->id,
                'status'           => $session->status,
            ]);
            return;
        }

        try {
            $service->finalizeSquadOrder($session, reason: 'deadline_elapsed');
        } catch (\Throwable $e) {
            Log::error('SQUAD_FINALIZE_FAILED', [
                'squad_session_id' => $session->id,
                'error'            => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
