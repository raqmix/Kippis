<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Models\SquadCartItem;
use App\Core\Models\SquadMember;
use App\Core\Models\SquadSession;
use App\Core\Models\Store;
use App\Http\Controllers\Controller;
use App\Services\SquadOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SquadController extends Controller
{
    public function __construct(private SquadOrderService $service) {}

    /** POST /api/v1/squad */
    public function create(Request $request): JsonResponse
    {
        $data     = $request->validate(['store_id' => ['required', 'integer']]);
        $customer = auth('api')->user();
        $store    = Store::findOrFail($data['store_id']);

        try {
            $session = $this->service->createSession($customer, $store);
        } catch (\DomainException $e) {
            return apiError('SQUAD_ERROR', $e->getMessage(), 422);
        }

        return apiSuccess(['session' => $this->formatSession($session)], 201);
    }

    /** POST /api/v1/squad/join */
    public function join(Request $request): JsonResponse
    {
        $data     = $request->validate(['invite_code' => ['required', 'string', 'max:8']]);
        $customer = auth('api')->user();

        try {
            $member = $this->service->joinSession($customer, strtoupper($data['invite_code']));
        } catch (\DomainException $e) {
            return apiError('SQUAD_ERROR', $e->getMessage(), 422);
        }

        $session = $member->session->load(['host', 'store', 'members']);
        return apiSuccess(['session' => $this->formatSession($session)]);
    }

    /** GET /api/v1/squad/{session} */
    public function show(SquadSession $session): JsonResponse
    {
        $this->authorizeAccess($session);
        $session->load(['host', 'store', 'members', 'cartItems.product', 'cartItems.member']);
        return apiSuccess(['session' => $this->formatSession($session)]);
    }

    /** DELETE /api/v1/squad/{session} */
    public function cancel(SquadSession $session): JsonResponse
    {
        $customer = auth('api')->user();
        try {
            $this->service->cancelSession($customer, $session);
        } catch (\DomainException $e) {
            return apiError('SQUAD_ERROR', $e->getMessage(), 422);
        }
        return apiSuccess(['message' => 'Session cancelled.']);
    }

    /** POST /api/v1/squad/{session}/lock */
    public function lock(SquadSession $session): JsonResponse
    {
        $customer = auth('api')->user();
        try {
            $this->service->lockSession($customer, $session);
        } catch (\DomainException $e) {
            return apiError('SQUAD_ERROR', $e->getMessage(), 422);
        }
        return apiSuccess(['message' => 'Session locked.']);
    }

    /** POST /api/v1/squad/{session}/checkout */
    public function checkout(Request $request, SquadSession $session): JsonResponse
    {
        $data     = $request->validate(['promo_code' => ['nullable', 'string']]);
        $customer = auth('api')->user();

        try {
            $order = $this->service->checkout($customer, $session, $data);
        } catch (\DomainException $e) {
            return apiError('SQUAD_ERROR', $e->getMessage(), 422);
        }

        return apiSuccess(['order_id' => $order->id, 'total' => $order->total]);
    }

    private function authorizeAccess(SquadSession $session): void
    {
        $customer = auth('api')->user();
        if (! $session->members()->where('customer_id', $customer->id)->exists()) {
            abort(403, 'You are not a member of this squad.');
        }
    }

    private function formatSession(SquadSession $session): array
    {
        $subtotal  = $session->relationLoaded('cartItems')
            ? $session->cartItems->sum(fn ($i) => $i->lineTotal())
            : 0;

        return [
            'id'          => $session->id,
            'invite_code' => $session->invite_code,
            'status'      => $session->status,
            'expires_at'  => $session->expires_at->toIso8601String(),
            'store'       => $session->relationLoaded('store')
                ? ['id' => $session->store->id, 'name_en' => $session->store->name_en]
                : null,
            'host'        => $session->relationLoaded('host')
                ? ['id' => $session->host->id, 'name' => $session->host->name]
                : null,
            'members'     => $session->relationLoaded('members')
                ? $session->members->map(fn ($m) => [
                    'id'          => $m->id,
                    'customer_id' => $m->customer_id,
                    'nickname'    => $m->nickname,
                    'is_host'     => $m->is_host,
                    'item_count'  => $session->relationLoaded('cartItems')
                        ? $session->cartItems->where('squad_member_id', $m->id)->count()
                        : 0,
                ])
                : [],
            'cart'        => $session->relationLoaded('cartItems') ? [
                'items'      => $session->cartItems->map(fn ($i) => [
                    'id'         => $i->id,
                    'product_id' => $i->product_id,
                    'name_en'    => $i->product->name_en ?? '',
                    'quantity'   => $i->quantity,
                    'unit_price' => $i->unit_price,
                    'line_total' => $i->lineTotal(),
                    'note'       => $i->note,
                    'member_id'  => $i->squad_member_id,
                ]),
                'subtotal'   => $subtotal,
                'item_count' => $session->cartItems->sum('quantity'),
            ] : null,
        ];
    }
}
