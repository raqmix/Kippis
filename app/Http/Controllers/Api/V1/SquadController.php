<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Models\SquadCartItem;
use App\Core\Models\SquadMember;
use App\Core\Models\SquadPayment;
use App\Core\Models\SquadSession;
use App\Core\Models\Store;
use App\Http\Controllers\Controller;
use App\Services\SquadOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SquadController extends Controller
{
    public function __construct(private SquadOrderService $service) {}

    /** GET /api/v1/squad — list current user's active squads (host or member) */
    public function index(): JsonResponse
    {
        $customer = auth('api')->user();

        $sessions = SquadSession::query()
            // Include split-pay-in-flight states so members in the pay
            // window still see the squad on their lobby.
            ->whereIn('status', ['open', 'locked', 'awaiting_payments', 'partially_paid'])
            ->where(function ($q) use ($customer) {
                $q->where('host_id', $customer->id)
                  ->orWhereHas('members', fn ($m) => $m->where('customer_id', $customer->id));
            })
            ->with(['host', 'store', 'members', 'cartItems.product', 'payments'])
            ->orderByDesc('updated_at')
            ->get();

        return apiSuccess([
            'squads' => $sessions->map(fn ($s) => $this->formatSession($s))->values(),
        ]);
    }

    /** POST /api/v1/squad — create a squad with no store assigned (set at checkout) */
    public function create(Request $request): JsonResponse
    {
        $customer = auth('api')->user();

        try {
            $session = $this->service->createSession($customer);
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
        $session->load(['host', 'store', 'members', 'cartItems.product', 'cartItems.member', 'payments']);
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

    /**
     * POST /api/v1/squad/{session}/leave
     *
     * A non-host member removes themselves from the squad. Host can't
     * call this — they have to use `cancel` (DELETE), which ends the
     * session for everyone. Membership row + their cart lines are
     * removed; the squad continues for the remaining members.
     */
    public function leave(SquadSession $session): JsonResponse
    {
        $customer = auth('api')->user();
        $membership = $session->members()->where('customer_id', $customer->id)->first();
        if (!$membership) {
            return apiError('SQUAD_ERROR', 'You are not a member of this squad.', 422);
        }
        if ($membership->is_host) {
            return apiError(
                'SQUAD_ERROR',
                'Hosts must cancel the squad instead of leaving.',
                422,
            );
        }
        if ($session->isAwaitingPayments()) {
            return apiError(
                'SQUAD_ERROR',
                'You cannot leave while payments are being collected.',
                422,
            );
        }

        // Drop this member's cart lines along with their membership
        // so totals reflect reality post-leave.
        DB::transaction(function () use ($session, $membership) {
            $session->cartItems()->where('squad_member_id', $membership->id)->delete();
            $membership->delete();
        });

        return apiSuccess(['message' => 'You left the squad.']);
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

    /**
     * POST /api/v1/squad/{session}/checkout — host triggers split-pay.
     *
     * Replaces the single-pay flow. Returns the updated session with
     * the new `payments[]` block + `payment_deadline_at`. Members get
     * an FCM push fan-out via NotifySquadPaymentRequestListener.
     */
    public function checkout(Request $request, SquadSession $session): JsonResponse
    {
        $data = $request->validate([
            'store_id'   => ['required', 'integer', 'exists:stores,id'],
            'promo_code' => ['nullable', 'string'],
        ]);
        $customer = auth('api')->user();
        $store    = Store::findOrFail($data['store_id']);

        try {
            $session = $this->service->initiateSplitCheckout($customer, $session, $store, $data);
        } catch (\DomainException $e) {
            return apiError('SQUAD_ERROR', $e->getMessage(), 422);
        }

        $session->load(['host', 'store', 'members', 'cartItems.product', 'payments']);
        return apiSuccess(['session' => $this->formatSession($session)]);
    }

    /**
     * POST /api/v1/squad/{session}/pay — caller opens MPGS for their share.
     * Returns the gateway credentials (session_id, session_js_url,
     * gateway_order_id) plus the idempotency_key the verify step needs.
     */
    public function startPayment(SquadSession $session): JsonResponse
    {
        $customer = auth('api')->user();
        try {
            $result = $this->service->startPayment($customer, $session);
        } catch (\DomainException $e) {
            return apiError('SQUAD_ERROR', $e->getMessage(), 422);
        }

        $payment = $result['payment'];
        return apiSuccess([
            'payment' => [
                'id'              => $payment->id,
                'idempotency_key' => $payment->idempotency_key,
                'share_piasters'  => $payment->share_piasters,
                'status'          => $payment->status,
            ],
            'gateway' => [
                'session_id'     => $result['session_id'],
                'session_js_url' => $result['session_js_url'],
                'gateway_order'  => $result['gateway_order'],
                'transaction_id' => $result['transaction_id'],
                'amount_egp'     => $result['amount_egp'],
                'currency'       => $result['currency'],
            ],
            'deadline_at' => $result['deadline_at'],
        ]);
    }

    /**
     * POST /api/v1/squad/{session}/pay/verify — caller submits the SDK
     * result. Server calls MPGS PAY, marks the row paid or failed,
     * triggers evaluate-finalize.
     */
    public function verifyPayment(Request $request, SquadSession $session): JsonResponse
    {
        $data = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $customer = auth('api')->user();
        try {
            $payment = $this->service->verifyPayment($customer, $session, $data);
        } catch (\DomainException $e) {
            return apiError('SQUAD_ERROR', $e->getMessage(), 422);
        }

        $session->refresh()->load(['host', 'store', 'members', 'cartItems.product', 'payments']);
        return apiSuccess([
            'payment' => [
                'id'             => $payment->id,
                'status'         => $payment->status,
                'failed_reason'  => $payment->failed_reason,
                'paid_at'        => optional($payment->paid_at)->toIso8601String(),
            ],
            'session' => $this->formatSession($session),
        ]);
    }

    /**
     * POST /api/v1/squad/{session}/push-now — host force-finalize with
     * whoever's already paid. Pending rows become timed_out.
     */
    public function pushNow(SquadSession $session): JsonResponse
    {
        $customer = auth('api')->user();
        try {
            $order = $this->service->pushNow($customer, $session);
        } catch (\DomainException $e) {
            return apiError('SQUAD_ERROR', $e->getMessage(), 422);
        }
        return apiSuccess(['order_id' => $order->id, 'total' => $order->total]);
    }

    /**
     * POST /api/v1/squad/{session}/skip-member — host marks one stuck
     * member as skipped. Their items drop from finalize.
     */
    public function skipMember(Request $request, SquadSession $session): JsonResponse
    {
        $data = $request->validate([
            'member_id' => ['required', 'integer', 'exists:squad_members,id'],
        ]);
        $customer = auth('api')->user();
        $member = SquadMember::where('id', $data['member_id'])
            ->where('squad_session_id', $session->id)
            ->firstOrFail();

        try {
            $payment = $this->service->skipMember($customer, $session, $member);
        } catch (\DomainException $e) {
            return apiError('SQUAD_ERROR', $e->getMessage(), 422);
        }

        return apiSuccess([
            'payment_id' => $payment->id,
            'status'     => $payment->status,
        ]);
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

        $payments = $session->relationLoaded('payments')
            ? $session->payments
            : collect();

        // Summary counts the UI uses to render the status board headline
        // ("3 paid · 1 pending · 0 dropped") without iterating client-side.
        $summary = [
            'paid'      => $payments->where('status', SquadPayment::STATUS_PAID)->count(),
            'pending'   => $payments->whereIn('status', [SquadPayment::STATUS_PENDING, SquadPayment::STATUS_PAYING])->count(),
            'failed'    => $payments->where('status', SquadPayment::STATUS_FAILED)->count(),
            'skipped'   => $payments->where('status', SquadPayment::STATUS_SKIPPED)->count(),
            'timed_out' => $payments->where('status', SquadPayment::STATUS_TIMED_OUT)->count(),
            'refunded'  => $payments->where('status', SquadPayment::STATUS_REFUNDED)->count(),
        ];

        return [
            'id'          => $session->id,
            'invite_code' => $session->invite_code,
            'status'      => $session->status,
            'expires_at'  => $session->expires_at->toIso8601String(),
            'payment_deadline_at' => optional($session->payment_deadline_at)->toIso8601String(),
            'payment_method'      => $session->payment_method,
            'store'       => $session->relationLoaded('store') && $session->store !== null
                ? ['id' => $session->store->id, 'name_en' => $session->store->getNameLocalized('en')]
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
                    'name_en'    => $i->product?->getName('en') ?? '',
                    'quantity'   => $i->quantity,
                    'unit_price' => $i->unit_price,
                    'line_total' => $i->lineTotal(),
                    'note'       => $i->note,
                    'member_id'  => $i->squad_member_id,
                ]),
                'subtotal'   => $subtotal,
                'item_count' => $session->cartItems->sum('quantity'),
            ] : null,
            'payments'    => $session->relationLoaded('payments')
                ? $payments->map(fn (SquadPayment $p) => [
                    'id'              => $p->id,
                    'member_id'       => $p->squad_member_id,
                    'share_piasters'  => $p->share_piasters,
                    'status'          => $p->status,
                    'paid_at'         => optional($p->paid_at)->toIso8601String(),
                    'failed_reason'   => $p->failed_reason,
                    'idempotency_key' => $p->idempotency_key,
                ])->values()
                : [],
            'payment_summary' => $summary,
        ];
    }
}
