<?php

namespace App\Console\Commands;

use App\Core\Models\Order;
use App\Events\OrderStatusUpdated;
use App\Integrations\Foodics\Exceptions\FoodicsException;
use App\Integrations\Foodics\Services\FoodicsClient;
use App\Integrations\Foodics\Support\FoodicsStatusMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Safety-net poll for Foodics order status updates. The primary path is the
 * webhook handled by FoodicsWebhookController; this poll catches anything
 * missed (webhook delivery failures, signature outages, etc.).
 *
 * Scope: orders that have a `foodics_order_id` AND are not in a terminal
 * Kippis status. Bounded to 100 per run to keep API usage sane.
 */
class FoodicsSyncOrderStatus extends Command
{
    protected $signature = 'foodics:sync-order-status {--limit=100}';

    protected $description = 'Poll Foodics for status updates on orders not yet in a terminal state.';

    public function handle(FoodicsClient $client): int
    {
        $limit = (int) $this->option('limit');

        $orders = Order::query()
            ->whereNotNull('foodics_order_id')
            ->whereNotIn('status', FoodicsStatusMapper::terminalStatuses())
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No orders to poll.');
            return self::SUCCESS;
        }

        $endpointBase = config('foodics.endpoints.orders', 'v5/orders');
        $updated = 0;
        $failed = 0;

        foreach ($orders as $order) {
            try {
                $response = $client->get("{$endpointBase}/{$order->foodics_order_id}");
            } catch (FoodicsException $e) {
                $failed++;
                Log::warning('FOODICS_POLL_ORDER_FAILED', [
                    'order_id' => $order->id,
                    'foodics_order_id' => $order->foodics_order_id,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            if (! $response->ok || ! is_array($response->data)) {
                $failed++;
                continue;
            }

            $data = is_array($response->data['data'] ?? null)
                ? $response->data['data']
                : $response->data;

            $rawStatus = $data['status'] ?? null;
            $newStatus = FoodicsStatusMapper::fromFoodics($rawStatus);

            if ($newStatus === null || $newStatus === $order->status) {
                continue;
            }

            $oldStatus = $order->status;
            $order->update(['status' => $newStatus]);
            event(new OrderStatusUpdated($order->fresh(), $oldStatus));
            $updated++;
        }

        $this->info("Polled {$orders->count()} order(s). Updated: {$updated}. Failed: {$failed}.");
        Log::info('FOODICS_POLL_RUN', [
            'polled' => $orders->count(),
            'updated' => $updated,
            'failed' => $failed,
        ]);

        return self::SUCCESS;
    }
}
