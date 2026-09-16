<?php

namespace App\Listeners;

use App\Core\Services\FcmService;
use App\Events\OrderStatusUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Sends an FCM push to the order's customer on every customer-visible
 * status transition (mixing → ready → completed → cancelled).
 *
 * Auto-discovered (App\Listeners namespace + typed handle method) —
 * Laravel's event auto-discovery registers it from the dispatched
 * OrderStatusUpdated event signature, do NOT register in
 * EventServiceProvider (would double-fire).
 *
 * Skips silently when:
 *   - No customer attached (kiosk/staff order)
 *   - Customer has no FCM token registered
 *   - Status transition has no customer-facing copy (e.g. internal
 *     pending_payment → received, which the customer already saw via
 *     the checkout success screen)
 */
class NotifyCustomerOnOrderStatusUpdate implements ShouldQueue
{
    public function __construct(private FcmService $fcm)
    {
    }

    public function handle(OrderStatusUpdated $event): void
    {
        $order = $event->order->loadMissing('customer');
        $customer = $order->customer;

        if (! $customer) {
            return;
        }

        $token = $customer->fcm_token;
        if (! $token) {
            return;
        }

        $copy = $this->copyFor($order->status);
        if ($copy === null) {
            // No customer-facing message for this transition (e.g.
            // received, pending_payment) — skip without logging noise.
            return;
        }

        try {
            $this->fcm->sendDataToToken(
                $token,
                title: $copy['title'],
                body: $copy['body'],
                data: [
                    'type'       => 'order_status',
                    'order_id'   => $order->id,
                    'pos_code'   => $order->pos_code,
                    'old_status' => $event->oldStatus,
                    'new_status' => $order->status,
                ],
            );
        } catch (\Throwable $e) {
            Log::error('ORDER_STATUS_PUSH_FAILED', [
                'order_id'   => $order->id,
                'new_status' => $order->status,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Bilingual (English + Arabic) per-status copy. Returns null when no
     * push should be sent for this status. Bilingual format is intentional —
     * we don't store the customer's preferred locale, and Egyptian users
     * are typically comfortable with either language.
     */
    private function copyFor(string $status): ?array
    {
        return match ($status) {
            'mixing' => [
                'title' => 'Your order is being prepared • جاري تحضير طلبك',
                'body'  => "We're mixing it now — hang tight! نقوم بتحضير طلبك الآن.",
            ],
            'ready' => [
                'title' => 'Your order is ready! • طلبك جاهز!',
                'body'  => 'Head to the counter to pick it up. توجه إلى المنضدة لاستلام طلبك.',
            ],
            'completed' => [
                'title' => 'Enjoy your Kippis! • استمتع بـ Kippis!',
                'body'  => 'Tap to rate your order and earn bonus points. قيّم طلبك واكسب نقاط إضافية.',
            ],
            'cancelled' => [
                'title' => 'Order cancelled • تم إلغاء الطلب',
                'body'  => 'Your order was cancelled. Tap for details. تم إلغاء طلبك. اضغط للتفاصيل.',
            ],
            default => null,
        };
    }
}
