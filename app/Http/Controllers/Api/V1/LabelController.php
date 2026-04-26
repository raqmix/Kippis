<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Models\Order;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LabelController extends Controller
{
    /**
     * GET /api/v1/orders/{order}/labels
     *
     * Return printable label data for all items in the order.
     */
    public function index(Order $order): JsonResponse
    {
        $this->authorizeOrderAccess($order);

        $labels = $this->buildLabels($order);

        return apiSuccess(['labels' => $labels]);
    }

    /**
     * GET /api/v1/orders/{order}/labels/{item}
     *
     * Return label data for a single item (0-indexed position in items_snapshot).
     */
    public function show(Order $order, int $item): JsonResponse
    {
        $this->authorizeOrderAccess($order);

        $items = $order->items_snapshot ?? [];
        if (! isset($items[$item])) {
            return apiError('NOT_FOUND', 'Item not found in order.', 404);
        }

        $label = $this->buildLabel($order, $items[$item], $item);

        return apiSuccess(['label' => $label]);
    }

    private function buildLabels(Order $order): array
    {
        $items = $order->items_snapshot ?? [];

        return array_values(array_map(
            fn (array $item, int $index) => $this->buildLabel($order, $item, $index),
            $items,
            array_keys($items),
        ));
    }

    private function buildLabel(Order $order, array $item, int $index): array
    {
        $modifiers = [];
        if (! empty($item['modifiers'])) {
            foreach ($item['modifiers'] as $mod) {
                $modifiers[] = $mod['name_en'] ?? $mod['name'] ?? (string) $mod;
            }
        }

        return [
            'order_item_id'    => $index,
            'pos_code'         => $order->pos_code,
            'product_name_en'  => $item['name_en'] ?? $item['name'] ?? '',
            'product_name_ar'  => $item['name_ar'] ?? '',
            'modifiers'        => $modifiers,
            'customer_name'    => $order->customer_name ?? 'Guest',
            'note'             => $item['note'] ?? $order->notes ?? '',
            'store_name'       => $order->store?->name_en ?? '',
            'timestamp'        => $order->created_at->toIso8601String(),
            'qr_data'          => "kippis://order/{$order->id}/item/{$index}",
        ];
    }

    /**
     * Ensure the authenticated customer owns the order (or it's a kiosk order).
     */
    private function authorizeOrderAccess(Order $order): void
    {
        $customer = auth('api')->user();
        if ($customer && $order->customer_id !== null && $order->customer_id !== $customer->id) {
            abort(403, 'Access denied.');
        }
    }
}
