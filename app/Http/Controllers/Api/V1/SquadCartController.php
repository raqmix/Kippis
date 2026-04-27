<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Models\SquadSession;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SquadCartController extends Controller
{
    /** GET /api/v1/squad/{session}/cart */
    public function show(SquadSession $session): JsonResponse
    {
        $customer = auth('api')->user();

        if (! $session->members()->where('customer_id', $customer->id)->exists()) {
            return apiError('NOT_MEMBER', 'You are not a member of this squad.', 403);
        }

        $session->load(['cartItems.product', 'cartItems.member.customer']);

        $items    = $session->cartItems;
        $subtotal = $items->sum(fn ($i) => $i->lineTotal());

        return apiSuccess([
            'cart' => [
                'items'      => $items->map(fn ($i) => [
                    'id'          => $i->id,
                    'product_id'  => $i->product_id,
                    'name_en'     => $i->product->name_en ?? '',
                    'name_ar'     => $i->product->name_ar ?? '',
                    'quantity'    => $i->quantity,
                    'unit_price'  => $i->unit_price,
                    'line_total'  => $i->lineTotal(),
                    'modifiers'   => $i->modifiers,
                    'note'        => $i->note,
                    'added_by'    => $i->member->nickname ?? null,
                    'member_id'   => $i->squad_member_id,
                ]),
                'subtotal'   => $subtotal,
                'item_count' => $items->sum('quantity'),
                'status'     => $session->status,
            ],
        ]);
    }
}
