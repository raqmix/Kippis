<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Models\CreatorDrop;
use App\Core\Models\Order;
use App\Core\Models\Store;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WidgetDataController extends Controller
{
    /**
     * GET /api/v1/widgets/data
     *
     * Lightweight endpoint for iOS/Android home screen widget refresh.
     */
    public function index(Request $request): JsonResponse
    {
        $customer = auth('api')->user();

        $lastOrder  = null;
        if ($customer) {
            $order = Order::where('customer_id', $customer->id)
                ->where('status', 'completed')
                ->latest()
                ->first();

            if ($order) {
                $lastOrder = [
                    'id'         => $order->id,
                    'store_name' => $order->store->name_en ?? '',
                    'item_count' => is_array($order->items_snapshot) ? count($order->items_snapshot) : 0,
                    'total'      => (int) ($order->total * 100),
                ];
            }
        }

        // Nearest store — return all stores, let the client decide by GPS
        $stores = Store::where('is_active', true)
            ->select('id', 'name_en', 'name_ar', 'latitude', 'longitude')
            ->get()
            ->map(fn ($s) => [
                'id'        => $s->id,
                'name_en'   => $s->name_en,
                'name_ar'   => $s->name_ar,
                'latitude'  => $s->latitude,
                'longitude' => $s->longitude,
            ]);

        // Next upcoming drop
        $nextDrop = CreatorDrop::where('status', 'scheduled')
            ->where('starts_at', '>', now())
            ->with('creator')
            ->orderBy('starts_at')
            ->first();

        $dropData = null;
        if ($nextDrop) {
            $dropData = [
                'id'             => $nextDrop->id,
                'title_en'       => $nextDrop->title_en,
                'title_ar'       => $nextDrop->title_ar,
                'starts_at'      => $nextDrop->starts_at->toIso8601String(),
                'creator_avatar' => $nextDrop->creator?->avatar,
                'creator_name'   => $nextDrop->creator?->name_en,
            ];
        }

        return apiSuccess([
            'last_order'    => $lastOrder,
            'stores'        => $stores,
            'next_drop'     => $dropData,
        ]);
    }
}
