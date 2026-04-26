<?php

namespace App\Http\Controllers\Api\Admin;

use App\Core\Models\Order;
use App\Core\Models\Store;
use App\Http\Controllers\Controller;
use App\Services\QueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QueueController extends Controller
{
    public function __construct(private readonly QueueService $queueService) {}

    /**
     * GET /api/admin/stores/{store}/queue
     *
     * Returns today's queue orders for the given store, grouped by status.
     */
    public function index(Store $store): JsonResponse
    {
        $grouped = $this->queueService->getStoreQueue($store);

        $result = $grouped->map(fn ($orders) => $orders->map(fn ($order) => [
            'id'            => $order->id,
            'pos_code'      => $order->pos_code,
            'status'        => $order->status,
            'customer_name' => $order->customer_name ?? 'Guest',
            'items_summary' => $this->itemsSummary($order),
            'item_count'    => is_array($order->items_snapshot) ? count($order->items_snapshot) : 0,
            'created_at'    => $order->created_at->toIso8601String(),
            'elapsed_seconds' => now()->diffInSeconds($order->created_at),
        ])->values()->all())->all();

        return apiSuccess([
            'store_id' => $store->id,
            'queue'    => $result,
        ]);
    }

    /**
     * POST /api/admin/orders/{order}/transition
     *
     * Transition an order to its next queue status.
     * Body: { "status": "preparing" | "ready" | "picked_up" }
     */
    public function transition(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:preparing,ready,picked_up'],
        ]);

        try {
            $this->queueService->transitionOrder($order, $data['status'], auth('admin')->user());
        } catch (\DomainException $e) {
            return apiError('INVALID_TRANSITION', $e->getMessage(), 422);
        }

        return apiSuccess([
            'order_id'   => $order->id,
            'pos_code'   => $order->pos_code,
            'new_status' => $data['status'],
        ]);
    }

    private function itemsSummary(Order $order): string
    {
        $items = $order->items_snapshot;
        if (! is_array($items) || empty($items)) {
            return '';
        }

        return collect($items)
            ->map(fn ($item) => ($item['quantity'] ?? 1) . '× ' . ($item['name_en'] ?? $item['name'] ?? 'Item'))
            ->implode(', ');
    }
}
