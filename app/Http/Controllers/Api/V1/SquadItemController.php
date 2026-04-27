<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Models\SquadCartItem;
use App\Core\Models\SquadSession;
use App\Http\Controllers\Controller;
use App\Services\SquadOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SquadItemController extends Controller
{
    public function __construct(private SquadOrderService $service) {}

    /** POST /api/v1/squad/{session}/items */
    public function store(Request $request, SquadSession $session): JsonResponse
    {
        $data   = $request->validate([
            'product_id'   => ['required', 'integer'],
            'product_kind' => ['nullable', 'in:standard,mix'],
            'quantity'     => ['nullable', 'integer', 'min:1', 'max:20'],
            'modifiers'    => ['nullable', 'array'],
            'note'         => ['nullable', 'string', 'max:255'],
        ]);

        $customer = auth('api')->user();
        $member   = $session->members()->where('customer_id', $customer->id)->first();

        if (! $member) {
            return apiError('NOT_MEMBER', 'You are not a member of this squad.', 403);
        }

        try {
            $item = $this->service->addItem($member, $data);
        } catch (\DomainException $e) {
            return apiError('SQUAD_ERROR', $e->getMessage(), 422);
        }

        return apiSuccess(['item' => $item->toArray()], 201);
    }

    /** PUT /api/v1/squad/{session}/items/{item} */
    public function update(Request $request, SquadSession $session, SquadCartItem $item): JsonResponse
    {
        $data     = $request->validate([
            'quantity'  => ['nullable', 'integer', 'min:1', 'max:20'],
            'modifiers' => ['nullable', 'array'],
            'note'      => ['nullable', 'string', 'max:255'],
        ]);

        $customer = auth('api')->user();
        $member   = $session->members()->where('customer_id', $customer->id)->first();

        if (! $member) {
            return apiError('NOT_MEMBER', 'You are not a member of this squad.', 403);
        }

        try {
            $updated = $this->service->updateItem($member, $item, $data);
        } catch (\DomainException $e) {
            return apiError('SQUAD_ERROR', $e->getMessage(), 422);
        }

        return apiSuccess(['item' => $updated->toArray()]);
    }

    /** DELETE /api/v1/squad/{session}/items/{item} */
    public function destroy(SquadSession $session, SquadCartItem $item): JsonResponse
    {
        $customer = auth('api')->user();
        $member   = $session->members()->where('customer_id', $customer->id)->first();

        if (! $member) {
            return apiError('NOT_MEMBER', 'You are not a member of this squad.', 403);
        }

        try {
            $this->service->removeItem($member, $item);
        } catch (\DomainException $e) {
            return apiError('SQUAD_ERROR', $e->getMessage(), 422);
        }

        return apiSuccess(['message' => 'Item removed.']);
    }
}
