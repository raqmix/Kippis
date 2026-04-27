<?php

namespace App\Http\Controllers\Api\V1\Kiosk;

use App\Core\Models\Order;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KioskReorderController extends Controller
{
    /**
     * GET /api/v1/kiosk/reorder/scan?qr_data={data}
     *
     * Look up a customer by loyalty wallet QR code and return their last order for this store.
     */
    public function scan(Request $request): JsonResponse
    {
        $request->validate(['qr_data' => ['required', 'string', 'max:255']]);
        $store = $request->attributes->get('kiosk_store');

        // QR payload can be a wallet_id, customer_id, or encoded deep link
        $qrData = $request->input('qr_data');

        // Try to find a loyalty wallet by the qr_data (wallet_id or encoded value)
        $wallet = \App\Core\Models\LoyaltyWallet::where('id', $qrData)
            ->orWhere('qr_code', $qrData)
            ->first();

        if (! $wallet) {
            return apiError('WALLET_NOT_FOUND', 'No wallet found for this QR code.', 404);
        }

        $lastOrder = Order::where('customer_id', $wallet->customer_id)
            ->where('store_id', $store->id)
            ->where('status', 'completed')
            ->latest()
            ->first();

        if (! $lastOrder) {
            return apiError('NO_PREVIOUS_ORDER', 'No previous orders found for this customer at this store.', 404);
        }

        return apiSuccess([
            'customer_name' => $wallet->customer->name ?? 'Customer',
            'order'         => [
                'id'            => $lastOrder->id,
                'pos_code'      => $lastOrder->pos_code,
                'total'         => $lastOrder->total,
                'items_summary' => $this->itemsSummary($lastOrder),
                'item_count'    => is_array($lastOrder->items_snapshot) ? count($lastOrder->items_snapshot) : 0,
                'items_snapshot'=> $lastOrder->items_snapshot,
                'created_at'    => $lastOrder->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/v1/kiosk/reorder/confirm
     *
     * Populate the kiosk cart with items from a previous order.
     */
    public function confirm(Request $request): JsonResponse
    {
        $data  = $request->validate(['order_id' => ['required', 'integer']]);
        $store = $request->attributes->get('kiosk_store');

        $order = Order::find($data['order_id']);

        if (! $order || $order->store_id !== $store->id) {
            return apiError('ORDER_NOT_FOUND', 'Order not found.', 404);
        }

        // Return items from the original order for the kiosk to load into cart
        return apiSuccess([
            'items' => $order->items_snapshot ?? [],
            'message' => 'Items ready to add to cart.',
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
