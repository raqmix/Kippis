<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Models\Order;
use App\Events\OrderStatusUpdated;
use App\Http\Controllers\Controller;
use App\Integrations\Foodics\Support\FoodicsStatusMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook receiver for Foodics → Kippis events. Mounted outside `auth:api`
 * because Foodics authenticates with HMAC signature, not a Bearer token.
 *
 * Configure the signing secret as `FOODICS_WEBHOOK_SECRET` and add the
 * matching value to the Foodics dashboard webhook config.
 */
class FoodicsWebhookController extends Controller
{
    /** POST /api/v1/webhooks/foodics/order-status */
    public function orderStatus(Request $request): JsonResponse
    {
        if (! $this->verifySignature($request)) {
            Log::warning('FOODICS_WEBHOOK_BAD_SIGNATURE', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'invalid signature'], 401);
        }

        $payload = $request->json()->all();

        // Foodics typically wraps the resource as { data: {...} } and may
        // include an "event" field. Tolerate both flat and nested.
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        $foodicsOrderId = $data['id'] ?? $data['order_id'] ?? null;
        $rawStatus = $data['status'] ?? $data['order_status'] ?? null;

        if (! $foodicsOrderId || $rawStatus === null) {
            Log::warning('FOODICS_WEBHOOK_INCOMPLETE_PAYLOAD', [
                'payload_keys' => array_keys($payload),
            ]);
            return response()->json(['error' => 'missing id or status'], 422);
        }

        $newStatus = FoodicsStatusMapper::fromFoodics($rawStatus);
        if ($newStatus === null) {
            Log::info('FOODICS_WEBHOOK_UNMAPPED_STATUS', [
                'foodics_order_id' => $foodicsOrderId,
                'raw_status' => $rawStatus,
            ]);
            // Ack so Foodics doesn't keep retrying an unmappable value.
            return response()->json(['ok' => true, 'note' => 'unmapped status']);
        }

        $order = Order::where('foodics_order_id', (string) $foodicsOrderId)->first();
        if (! $order) {
            Log::info('FOODICS_WEBHOOK_ORDER_NOT_FOUND', [
                'foodics_order_id' => $foodicsOrderId,
            ]);
            return response()->json(['ok' => true, 'note' => 'order not found']);
        }

        if ($order->status === $newStatus) {
            return response()->json(['ok' => true, 'note' => 'no change']);
        }

        $oldStatus = $order->status;
        $order->update(['status' => $newStatus]);

        event(new OrderStatusUpdated($order->fresh(), $oldStatus));

        Log::info('FOODICS_WEBHOOK_STATUS_UPDATED', [
            'order_id' => $order->id,
            'foodics_order_id' => $foodicsOrderId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Verify HMAC SHA256 of the raw request body against the configured
     * shared secret. Foodics sends the signature in the X-Foodics-Signature
     * header (or X-Webhook-Signature — accept either to keep this resilient).
     */
    private function verifySignature(Request $request): bool
    {
        $secret = config('foodics.webhook_secret');
        if (! $secret) {
            // Fail closed if no secret is configured — refuse rather than
            // accept unauthenticated POSTs.
            Log::error('FOODICS_WEBHOOK_NO_SECRET_CONFIGURED');
            return false;
        }

        $signature = $request->header('X-Foodics-Signature')
            ?? $request->header('X-Webhook-Signature')
            ?? '';

        if ($signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
