<?php

namespace App\Http\Controllers\Api\V1\Kiosk;

use App\Core\Models\Order;
use App\Core\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KioskTopPicksController extends Controller
{
    /**
     * GET /api/v1/kiosk/top-picks
     *
     * Returns top 6 products by order count at this store in the last 30 days.
     */
    public function index(Request $request): JsonResponse
    {
        $store = $request->attributes->get('kiosk_store');

        // Gather product IDs from completed orders in the last 30 days
        $recentOrders = Order::where('store_id', $store->id)
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subDays(30))
            ->get(['items_snapshot']);

        // Count occurrences of each product_id across items_snapshot
        $productCounts = [];
        foreach ($recentOrders as $order) {
            foreach ($order->items_snapshot ?? [] as $item) {
                $pid = $item['product_id'] ?? null;
                if ($pid) {
                    $productCounts[$pid] = ($productCounts[$pid] ?? 0) + ($item['quantity'] ?? 1);
                }
            }
        }

        arsort($productCounts);
        $topIds = array_slice(array_keys($productCounts), 0, 6);

        $products = Product::whereIn('id', $topIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        // Return in order of frequency
        $topProducts = collect($topIds)
            ->filter(fn ($id) => isset($products[$id]))
            ->map(fn ($id) => [
                'id'          => $products[$id]->id,
                'name_en'     => $products[$id]->name_en,
                'name_ar'     => $products[$id]->name_ar,
                'price'       => $products[$id]->price,
                'image'       => $products[$id]->image,
                'order_count' => $productCounts[$id],
            ])
            ->values();

        return apiSuccess(['top_picks' => $topProducts]);
    }
}
