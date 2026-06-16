<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Models\RedeemItem;
use App\Core\Models\Setting;
use App\Http\Controllers\Controller;
use App\Services\RedeemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RedeemController extends Controller
{
    public function __construct(private RedeemService $service) {}

    /** GET /api/v1/loyalty/redeem-items?store_id=… */
    public function items(Request $request): JsonResponse
    {
        $locale  = app()->getLocale();
        $storeId = $request->query('store_id') ? (int) $request->query('store_id') : null;
        $items   = $this->service->availableItems($storeId);

        return apiSuccess([
            'redemption_enabled' => (bool) Setting::get('loyalty.redemption_enabled', true),
            'points_to_egp_rate' => (int) Setting::get('loyalty.points_to_egp_rate', 10),
            'max_points_per_order' => (int) Setting::get('loyalty.max_points_per_order', 0),
            'items' => $items->map(fn (RedeemItem $i) => [
                'id'           => $i->id,
                'title'        => $i->getTitle($locale),
                'title_en'     => $i->getTitle('en'),
                'title_ar'     => $i->getTitle('ar'),
                'description'  => $i->getDescription($locale),
                'image'        => $i->image,
                'points_cost'  => $i->points_cost,
                'product_id'   => $i->product_id,
                'wallet_ttl_days' => $i->wallet_ttl_days,
                'branch_scope' => $i->stores->isEmpty() ? 'all' : 'limited',
                'store_ids'    => $i->stores->pluck('id'),
            ])->values(),
        ]);
    }

    /** POST /api/v1/loyalty/redeem  body: { item_id, store_id? } */
    public function claim(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_id'  => ['required', 'integer', 'exists:redeem_items,id'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
        ]);

        $customer = auth('api')->user();
        $item = RedeemItem::findOrFail($data['item_id']);

        try {
            $entry = $this->service->claim($customer, $item, $data['store_id'] ?? null);
        } catch (\DomainException $e) {
            return apiError('REDEEM_ERROR', $e->getMessage(), 422);
        }

        return apiSuccess([
            'wallet_item' => [
                'id'             => $entry->id,
                'redeem_item_id' => $entry->redeem_item_id,
                'title'          => $entry->getTitle(app()->getLocale()),
                'points_spent'   => $entry->points_spent,
                'status'         => $entry->status,
                'expires_at'     => optional($entry->expires_at)->toIso8601String(),
            ],
        ], 201);
    }

    /** GET /api/v1/loyalty/wallet-items */
    public function wallet(Request $request): JsonResponse
    {
        $customer = auth('api')->user();
        $locale   = app()->getLocale();
        $items    = $this->service->walletForCustomer($customer);

        return apiSuccess([
            'wallet_items' => $items->map(fn ($w) => [
                'id'             => $w->id,
                'redeem_item_id' => $w->redeem_item_id,
                'product_id'     => $w->redeemItem?->product_id,
                'title'          => $w->getTitle($locale),
                'points_spent'   => $w->points_spent,
                'expires_at'     => optional($w->expires_at)->toIso8601String(),
                'created_at'     => $w->created_at->toIso8601String(),
            ])->values(),
        ]);
    }
}
