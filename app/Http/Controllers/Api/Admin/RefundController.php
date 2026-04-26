<?php

namespace App\Http\Controllers\Api\Admin;

use App\Core\Models\Order;
use App\Http\Controllers\Controller;
use App\Services\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RefundController extends Controller
{
    public function __construct(private readonly RefundService $refundService) {}

    /**
     * Void an order (admin only, pre-fulfillment).
     */
    public function void(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $refund = $this->refundService->void($order, auth('admin')->user(), $data['reason']);
        } catch (\DomainException $e) {
            return apiError('REFUND_NOT_ALLOWED', $e->getMessage(), 422);
        }

        return apiSuccess(['refund' => $this->refundData($refund)], 201);
    }

    /**
     * Full or partial refund of a completed order (admin only).
     */
    public function refund(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'type'   => ['required', 'in:full,partial'],
            'amount' => ['required_if:type,partial', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            if ($data['type'] === 'full') {
                $refund = $this->refundService->refundFull($order, auth('admin')->user(), $data['reason']);
            } else {
                $refund = $this->refundService->refundPartial($order, auth('admin')->user(), (int) $data['amount'], $data['reason']);
            }
        } catch (\DomainException $e) {
            return apiError('REFUND_NOT_ALLOWED', $e->getMessage(), 422);
        }

        return apiSuccess(['refund' => $this->refundData($refund)], 201);
    }

    /**
     * List all refunds for an order.
     */
    public function history(Order $order): JsonResponse
    {
        $refunds = $order->refunds()->with('admin:id,name')->latest()->get();

        return apiSuccess([
            'refunds'        => $refunds->map(fn ($r) => $this->refundData($r))->values(),
            'refund_status'  => $order->refund_status,
            'refunded_amount'=> $order->refunded_amount,
        ]);
    }

    private function refundData(\App\Core\Models\Refund $refund): array
    {
        return [
            'id'                => $refund->id,
            'type'              => $refund->type,
            'amount'            => $refund->amount,
            'reason'            => $refund->reason,
            'status'            => $refund->status,
            'gateway_reference' => $refund->gateway_reference,
            'processed_at'      => $refund->processed_at?->toIso8601String(),
            'admin'             => $refund->admin ? ['id' => $refund->admin->id, 'name' => $refund->admin->name] : null,
            'created_at'        => $refund->created_at->toIso8601String(),
        ];
    }
}
