<?php

namespace App\Listeners;

use App\Core\Models\SquadPayment;
use App\Core\Services\FcmService;
use App\Events\SquadCheckoutInitiated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Fans out an FCM push to every squad member when the host triggers
 * split-pay checkout. Each member gets a deep-link payload that opens
 * the SquadPayShareScreen with their share preloaded.
 *
 * Auto-discovered (App\Listeners namespace + handle method) — do NOT
 * register in EventServiceProvider, that double-fires (see existing
 * comment in EventServiceProvider re: OrderCreated).
 */
class NotifySquadPaymentRequestListener implements ShouldQueue
{
    public function __construct(private FcmService $fcm)
    {
    }

    public function handle(SquadCheckoutInitiated $event): void
    {
        $session = $event->session->loadMissing('payments.member.customer');

        foreach ($session->payments as $payment) {
            if ($payment->status !== SquadPayment::STATUS_PENDING) {
                continue;
            }

            $customer = $payment->member?->customer;
            $token = $customer?->fcm_token;
            if (! $token) {
                // No token registered — member will still see the pending
                // share when they open the squad in-app, but no push prompt.
                Log::info('SQUAD_PAY_PUSH_SKIPPED_NO_TOKEN', [
                    'squad_session_id' => $session->id,
                    'squad_member_id'  => $payment->squad_member_id,
                ]);
                continue;
            }

            $shareEgp = number_format($payment->share_piasters / 100, 2);

            try {
                $this->fcm->sendDataToToken(
                    $token,
                    title: 'Your squad ordered together',
                    body: "Tap to pay your share — {$shareEgp} EGP",
                    data: [
                        'type'                 => 'squad_pay_request',
                        'squad_session_id'     => $session->id,
                        'squad_payment_id'     => $payment->id,
                        'share_piasters'       => $payment->share_piasters,
                        'deadline_at'          => optional($session->payment_deadline_at)->toIso8601String(),
                    ],
                );
            } catch (\Throwable $e) {
                Log::error('SQUAD_PAY_PUSH_FAILED', [
                    'squad_session_id' => $session->id,
                    'squad_member_id'  => $payment->squad_member_id,
                    'error'            => $e->getMessage(),
                ]);
            }
        }
    }
}
