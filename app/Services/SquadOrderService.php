<?php

namespace App\Services;

use App\Core\Models\Customer;
use App\Core\Models\Order;
use App\Core\Models\Product;
use App\Core\Models\SquadCartItem;
use App\Core\Models\SquadMember;
use App\Core\Models\SquadPayment;
use App\Core\Models\SquadSession;
use App\Core\Models\Store;
use App\Events\OrderCreated;
use App\Events\SquadCheckoutInitiated;
use App\Events\SquadEvent;
use App\Jobs\FinalizeSquadOrderJob;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SquadOrderService
{
    private const MAX_MEMBERS = 10;
    private const SESSION_TTL_MINUTES = 30;
    private const PAYMENT_WINDOW_MINUTES = 5;

    public function __construct(private MastercardPaymentService $mastercard)
    {
    }

    /**
     * Create a squad with no store assigned. The host picks a store at checkout.
     */
    public function createSession(Customer $host): SquadSession
    {
        $session = SquadSession::create([
            'host_id'     => $host->id,
            'store_id'    => null,
            'invite_code' => $this->generateInviteCode(),
            'status'      => 'open',
            'expires_at'  => now()->addMinutes(self::SESSION_TTL_MINUTES),
        ]);

        SquadMember::create([
            'squad_session_id' => $session->id,
            'customer_id'      => $host->id,
            'nickname'         => $host->name,
            'joined_at'        => now(),
            'is_host'          => true,
        ]);

        return $session->load(['host', 'store', 'members']);
    }

    public function joinSession(Customer $customer, string $inviteCode): SquadMember
    {
        $session = SquadSession::where('invite_code', $inviteCode)
            ->where('status', 'open')
            ->first();

        if (! $session) {
            throw new \DomainException('Session not found or is no longer open.');
        }

        if ($session->isExpired()) {
            throw new \DomainException('This squad session has expired.');
        }

        if ($session->members()->count() >= self::MAX_MEMBERS) {
            throw new \DomainException('This squad is full (max ' . self::MAX_MEMBERS . ' members).');
        }

        if ($session->members()->where('customer_id', $customer->id)->exists()) {
            throw new \DomainException('You are already in this squad.');
        }

        $member = SquadMember::create([
            'squad_session_id' => $session->id,
            'customer_id'      => $customer->id,
            'nickname'         => $customer->name,
            'joined_at'        => now(),
            'is_host'          => false,
        ]);

        broadcast(new SquadEvent($session->id, 'SquadMemberJoined', [
            'member_id'  => $member->id,
            'nickname'   => $member->nickname,
            'joined_at'  => $member->joined_at->toIso8601String(),
        ]));

        return $member;
    }

    public function addItem(SquadMember $member, array $dto): SquadCartItem
    {
        $session = $member->session;

        if ($session->status !== 'open') {
            throw new \DomainException('Cart is locked or session is closed.');
        }

        if ($session->isExpired()) {
            throw new \DomainException('This squad session has expired.');
        }

        $product = Product::findOrFail($dto['product_id']);
        $unitPrice = Money::toPiasters((float) $product->base_price);

        $item = SquadCartItem::create([
            'squad_session_id' => $session->id,
            'squad_member_id'  => $member->id,
            'product_id'       => $product->id,
            'product_kind'     => $dto['product_kind'] ?? 'standard',
            'quantity'         => $dto['quantity'] ?? 1,
            'modifiers'        => $dto['modifiers'] ?? null,
            'note'             => $dto['note'] ?? null,
            'unit_price'       => $unitPrice,
        ]);

        // Reset TTL on activity
        $session->update(['expires_at' => now()->addMinutes(self::SESSION_TTL_MINUTES)]);

        broadcast(new SquadEvent($session->id, 'SquadCartUpdated', $this->cartPayload($session)));

        return $item;
    }

    public function updateItem(SquadMember $member, SquadCartItem $item, array $dto): SquadCartItem
    {
        if ($item->squad_member_id !== $member->id) {
            throw new \DomainException('You can only edit your own items.');
        }

        $session = $member->session;
        if ($session->status !== 'open') {
            throw new \DomainException('Cart is locked.');
        }

        $item->update(array_filter([
            'quantity'  => $dto['quantity'] ?? $item->quantity,
            'modifiers' => $dto['modifiers'] ?? $item->modifiers,
            'note'      => $dto['note'] ?? $item->note,
        ], fn ($v) => $v !== null));

        broadcast(new SquadEvent($session->id, 'SquadCartUpdated', $this->cartPayload($session)));

        return $item->fresh();
    }

    public function removeItem(SquadMember $member, SquadCartItem $item): void
    {
        // Host can remove any item; others only their own
        if (! $member->is_host && $item->squad_member_id !== $member->id) {
            throw new \DomainException('You can only remove your own items.');
        }

        $sessionId = $item->squad_session_id;
        $item->delete();
        $session = SquadSession::find($sessionId);

        if ($session) {
            broadcast(new SquadEvent($session->id, 'SquadCartUpdated', $this->cartPayload($session)));
        }
    }

    public function lockSession(Customer $host, SquadSession $session): void
    {
        $this->guardIsHost($host, $session);

        if ($session->cartItems()->count() === 0) {
            throw new \DomainException('Cart is empty.');
        }

        $session->update(['status' => 'locked', 'locked_at' => now()]);

        broadcast(new SquadEvent($session->id, 'SquadLocked', [
            'locked_at'  => $session->locked_at->toIso8601String(),
            'locked_by'  => $host->name,
        ]));
    }

    // -----------------------------------------------------------------------
    // Split-payment checkout flow
    // -----------------------------------------------------------------------

    /**
     * Host opens checkout: locks the cart, builds per-member shares,
     * inserts a SquadPayment row per member with a SHARED gateway_order_id
     * (one Order on Foodics per squad), transitions the session to
     * `awaiting_payments`, and dispatches the deadline job + FCM fan-out.
     *
     * The host's own share is created the same way — they're prompted to
     * pay first, then everyone else.
     */
    public function initiateSplitCheckout(Customer $host, SquadSession $session, Store $store, array $dto): SquadSession
    {
        $this->guardIsHost($host, $session);

        if (! in_array($session->status, ['open', 'locked'], true)) {
            throw new \DomainException('This squad cannot start checkout from its current state.');
        }

        $items = $session->cartItems()->with('product')->get();
        if ($items->isEmpty()) {
            throw new \DomainException('Cart is empty.');
        }

        $perMember = $items->groupBy('squad_member_id')->map(
            fn ($lines) => $lines->sum(fn (SquadCartItem $i) => $i->lineTotal())
        );

        if ($perMember->isEmpty()) {
            throw new \DomainException('Nothing to pay for.');
        }

        $deadline = now()->addMinutes(self::PAYMENT_WINDOW_MINUTES);

        DB::transaction(function () use ($session, $store, $perMember, $deadline) {
            if ($session->store_id === null) {
                $session->update(['store_id' => $store->id]);
            }

            foreach ($perMember as $memberId => $sharePiasters) {
                SquadPayment::create([
                    'squad_session_id' => $session->id,
                    'squad_member_id'  => $memberId,
                    'share_piasters'   => (int) $sharePiasters,
                    'status'           => SquadPayment::STATUS_PENDING,
                ]);
            }

            $session->update([
                'status'              => 'awaiting_payments',
                'payment_method'      => 'card',
                'payment_deadline_at' => $deadline,
            ]);
        });

        $session->refresh()->load('payments.member.customer');

        // Hard deadline — fires the finalizer if nothing else has by then.
        // Cache::lock + isAwaitingPayments() guard makes this race-safe
        // against host's manual push-now.
        FinalizeSquadOrderJob::dispatch($session->id)->delay($deadline);

        // Listener fans out FCM pushes; SquadEvent broadcasts for sockets.
        SquadCheckoutInitiated::dispatch($session);
        broadcast(new SquadEvent($session->id, 'SquadCheckoutInitiated', [
            'payment_deadline_at' => $deadline->toIso8601String(),
            'payments'            => $session->payments->map(fn ($p) => [
                'id'              => $p->id,
                'member_id'       => $p->squad_member_id,
                'share_piasters'  => $p->share_piasters,
                'status'          => $p->status,
            ])->values(),
        ]));

        return $session;
    }

    /**
     * Member starts paying their share: we mint a fresh MPGS Hosted
     * Session for this attempt and return the credentials the SDK needs.
     * Returns the SquadPayment + gateway session details.
     *
     * Each call mints a NEW MPGS session (and gateway_order_id if the
     * row was previously failed) — that way retries after a decline get
     * a clean transaction, matching MPGS' one-PAY-per-session rule.
     */
    public function startPayment(Customer $caller, SquadSession $session): array
    {
        if (! $session->isAwaitingPayments()) {
            throw new \DomainException('This squad is not collecting payments right now.');
        }

        $member = $session->members()->where('customer_id', $caller->id)->first();
        if (! $member) {
            throw new \DomainException('You are not a member of this squad.');
        }

        $payment = $session->activePaymentForMember($member->id);
        if (! $payment) {
            // No active row — either resolved (paid, skipped, refunded) or
            // never created. Allow a retry from a clean slate when the
            // last attempt failed/timed_out.
            $lastFailed = $session->payments()
                ->where('squad_member_id', $member->id)
                ->whereIn('status', [SquadPayment::STATUS_FAILED, SquadPayment::STATUS_TIMED_OUT])
                ->latest()
                ->first();

            if (! $lastFailed) {
                throw new \DomainException('No payment is pending for you.');
            }

            // Recreate a fresh pending row off the failed one's share.
            $payment = SquadPayment::create([
                'squad_session_id' => $session->id,
                'squad_member_id'  => $member->id,
                'share_piasters'   => $lastFailed->share_piasters,
                'status'           => SquadPayment::STATUS_PENDING,
            ]);
        }

        if ($payment->isPaid()) {
            throw new \DomainException('Your share is already paid.');
        }

        // Mint the MPGS session. Each row gets its own session id; we
        // re-use the gateway_order_id across retries on the SAME row so
        // MPGS treats them as one logical order.
        if (empty($payment->gateway_order_id)) {
            $payment->gateway_order_id = $this->generateGatewayOrderId();
        }
        $payment->mastercard_session_id     = $this->mintMastercardSession();
        $payment->mastercard_transaction_id = (string) Str::uuid();
        $payment->status                    = SquadPayment::STATUS_PAYING;
        $payment->failed_reason             = null;
        $payment->save();

        return [
            'payment'        => $payment,
            'session_id'     => $payment->mastercard_session_id,
            'gateway_order'  => $payment->gateway_order_id,
            'transaction_id' => $payment->mastercard_transaction_id,
            'session_js_url' => $this->mastercardSessionJsUrl(),
            'amount_egp'     => number_format($payment->share_piasters / 100, 2),
            'currency'       => 'EGP',
            'deadline_at'    => optional($session->payment_deadline_at)->toIso8601String(),
        ];
    }

    /**
     * Member submits the SDK result. We hit MPGS' INITIATE_AUTHENTICATION
     * (and AUTHENTICATE_PAYER if needed, but for native SDKs the client
     * already finishes 3DS — so this just calls PAY), record the outcome
     * on the SquadPayment, and trigger an evaluate-finalize.
     *
     * Idempotency: if the same idempotency_key arrives twice we return
     * the cached result, no double-PAY.
     */
    public function verifyPayment(Customer $caller, SquadSession $session, array $dto): SquadPayment
    {
        $payment = SquadPayment::where('idempotency_key', $dto['idempotency_key'] ?? '')->first();
        if (! $payment || $payment->squad_session_id !== $session->id) {
            throw new \DomainException('Unknown payment.');
        }

        $member = $session->members()->where('customer_id', $caller->id)->first();
        if (! $member || $payment->squad_member_id !== $member->id) {
            throw new \DomainException('You can only verify your own payment.');
        }

        // Idempotent return — terminal status, no further work.
        if ($payment->isResolved()) {
            return $payment;
        }

        if (! $session->isAwaitingPayments()) {
            // Session already finalized / cancelled — abandon the in-flight
            // attempt. Don't call PAY on MPGS, the order's gone.
            $payment->update([
                'status'        => SquadPayment::STATUS_FAILED,
                'failed_reason' => 'session_no_longer_collecting',
            ]);
            $this->broadcastPaymentUpdate($session, $payment);
            return $payment;
        }

        $shareMajor = number_format($payment->share_piasters / 100, 2, '.', '');

        $payResult = $this->mastercard->pay(
            gatewayOrderId: $payment->gateway_order_id,
            transactionId:  $payment->mastercard_transaction_id,
            amount:         $shareMajor,
            currency:       'EGP',
            sessionId:      $payment->mastercard_session_id,
        );

        if ($payResult['success']) {
            $payment->update([
                'status'  => SquadPayment::STATUS_PAID,
                'paid_at' => now(),
            ]);
        } else {
            $payment->update([
                'status'        => SquadPayment::STATUS_FAILED,
                'failed_reason' => $payResult['message'] ?? ($payResult['error'] ?? 'pay_failed'),
            ]);
        }

        $this->broadcastPaymentUpdate($session->fresh(), $payment);
        $this->evaluateFinalize($session->fresh());

        return $payment->fresh();
    }

    /**
     * Host marks a stuck member as skipped — their items drop from the
     * finalize step. Same-session-only; cannot skip a member whose
     * payment is already `paid` (that would lose their money). Triggers
     * an evaluate-finalize so a single-remaining-pending skip auto-pushes.
     */
    public function skipMember(Customer $host, SquadSession $session, SquadMember $member): SquadPayment
    {
        $this->guardIsHost($host, $session);
        if (! $session->isAwaitingPayments()) {
            throw new \DomainException('Squad is no longer collecting payments.');
        }

        $payment = $session->activePaymentForMember($member->id);
        if (! $payment) {
            throw new \DomainException('No active payment for that member.');
        }
        if ($payment->isPaid()) {
            throw new \DomainException('Cannot skip a paid member.');
        }

        $payment->update([
            'status'        => SquadPayment::STATUS_SKIPPED,
            'failed_reason' => 'skipped_by_host',
        ]);
        $this->broadcastPaymentUpdate($session->fresh(), $payment);
        $this->evaluateFinalize($session->fresh());

        return $payment;
    }

    /**
     * Host clicks "Push now" — drop every still-pending payment to
     * timed_out and run the finalizer with whoever's already paid.
     * Requires at least one paid member; otherwise tell the host to wait
     * (we don't push empty orders).
     */
    public function pushNow(Customer $host, SquadSession $session): Order
    {
        $this->guardIsHost($host, $session);
        if (! $session->isAwaitingPayments()) {
            throw new \DomainException('Squad is no longer collecting payments.');
        }
        if ($session->payments()->where('status', SquadPayment::STATUS_PAID)->doesntExist()) {
            throw new \DomainException('Nothing paid yet — wait for at least one share.');
        }

        return $this->finalizeSquadOrder($session, reason: 'host_push_now');
    }

    /**
     * Squad cancellation. If any payments are charged, refund them via
     * MPGS first; only flip status when refunds resolve. Refund failures
     * are logged but don't abort the cancel — operations can sweep later.
     */
    public function cancelSession(Customer $host, SquadSession $session): void
    {
        $this->guardIsHost($host, $session);

        if (in_array($session->status, ['checked_out', 'cancelled'], true)) {
            throw new \DomainException('Squad already closed.');
        }

        $lock = Cache::lock('squad_finalize:' . $session->id, 60);
        if (! $lock->get()) {
            throw new \DomainException('Squad is being finalized — try again in a moment.');
        }

        try {
            $session->refresh();

            // Refund any charged shares before flipping the session
            // closed. Best-effort: failures are logged but the cancel
            // still proceeds (we don't want a gateway hiccup to leave
            // hosts stranded with a frozen squad).
            $charged = $session->payments()
                ->whereIn('status', SquadPayment::CHARGED_STATUSES)
                ->get();

            foreach ($charged as $payment) {
                $this->refundPayment($payment, reason: 'session_cancelled');
            }

            // Any still-pending rows: abandon them.
            $session->payments()
                ->whereIn('status', [SquadPayment::STATUS_PENDING, SquadPayment::STATUS_PAYING])
                ->update([
                    'status'        => SquadPayment::STATUS_FAILED,
                    'failed_reason' => 'session_cancelled',
                ]);

            $session->update(['status' => 'cancelled']);
        } finally {
            $lock->release();
        }

        broadcast(new SquadEvent($session->id, 'SquadCancelled', [
            'cancelled_by' => $host->name,
        ]));
    }

    /**
     * The atomic finalizer. Called from three entry points:
     *   - FinalizeSquadOrderJob (deadline elapsed)
     *   - pushNow() (host force-finalize)
     *   - evaluateFinalize() (all payments resolved)
     *
     * Guarded by Cache::lock so the three can race safely — first one
     * wins, the rest no-op. Builds items_snapshot from PAID members
     * only, creates the Order, fires OrderCreated (→ PushOrderToFoodics),
     * flips session → checked_out.
     */
    public function finalizeSquadOrder(SquadSession $session, string $reason = 'auto'): Order
    {
        $lock = Cache::lock('squad_finalize:' . $session->id, 120);
        if (! $lock->get()) {
            throw new \DomainException('Finalize already in progress.');
        }

        try {
            $session->refresh();
            if (! $session->isAwaitingPayments()) {
                // Race winner already finalized or cancelled — return the
                // existing order if there is one, else surface a clear error.
                if ($session->order_id) {
                    return Order::findOrFail($session->order_id);
                }
                throw new \DomainException('Session is not in a finalizable state.');
            }

            // Reason='deadline_elapsed' or 'host_push_now' both flush any
            // still-pending payments. The 'auto' path (last-member-resolved)
            // doesn't need this since by definition nothing's pending.
            if ($reason !== 'auto') {
                $session->payments()
                    ->whereIn('status', [SquadPayment::STATUS_PENDING, SquadPayment::STATUS_PAYING])
                    ->update([
                        'status'        => SquadPayment::STATUS_TIMED_OUT,
                        'failed_reason' => $reason === 'host_push_now' ? 'skipped_by_push_now' : 'deadline_elapsed',
                    ]);
            }

            $paidMemberIds = $session->paidMemberIds();
            if (empty($paidMemberIds)) {
                // Everyone failed/skipped/timed_out — no order to push.
                $session->update(['status' => 'cancelled']);
                broadcast(new SquadEvent($session->id, 'SquadCancelled', [
                    'cancelled_by' => 'system',
                    'reason'       => 'no_payments',
                ]));
                throw new \DomainException('No paid members — nothing to push to Foodics.');
            }

            $items = $session->cartItems()
                ->with('product')
                ->whereIn('squad_member_id', $paidMemberIds)
                ->get();

            $itemsSnapshot = $items->map(fn (SquadCartItem $item) => [
                'product_id'      => $item->product_id,
                'name_en'         => $item->product?->getName('en') ?? '',
                'name_ar'         => $item->product?->getName('ar') ?? '',
                'quantity'        => $item->quantity,
                'unit_price'      => $item->unit_price,
                'line_total'      => $item->lineTotal(),
                'modifiers'       => $item->modifiers ?? [],
                'note'            => $item->note,
                'squad_member_id' => $item->squad_member_id,
                'product_kind'    => $item->product_kind,
            ])->toArray();

            $subtotal = $items->sum(fn ($i) => $i->lineTotal());

            $order = Order::create([
                'customer_id'    => $session->host_id,
                'store_id'       => $session->store_id,
                'status'         => 'pending',
                'items_snapshot' => $itemsSnapshot,
                'total'          => $subtotal / 100,
                'metadata'       => [
                    'squad_session_id'    => $session->id,
                    'paid_member_count'   => count($paidMemberIds),
                    'finalize_reason'     => $reason,
                    'payment_method'      => 'card',
                ],
            ]);

            $session->update([
                'status'   => 'checked_out',
                'order_id' => $order->id,
            ]);

            // Fires the OrderCreated listener chain → PushOrderToFoodics.
            // DB::afterCommit not needed: we're outside an outer transaction.
            event(new OrderCreated($order));

            broadcast(new SquadEvent($session->id, 'SquadCheckedOut', [
                'order_id' => $order->id,
                'total'    => $subtotal,
                'reason'   => $reason,
            ]));

            return $order;
        } finally {
            $lock->release();
        }
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    private function evaluateFinalize(SquadSession $session): void
    {
        if (! $session->isAwaitingPayments()) {
            return;
        }

        // Last pending resolved → auto-finalize. If everyone failed,
        // finalizeSquadOrder() flips to cancelled itself.
        if ($session->allPaymentsResolved()) {
            try {
                $this->finalizeSquadOrder($session, reason: 'auto');
            } catch (\DomainException $e) {
                // Race lost or no paid members — handled inside finalize.
                Log::info('SQUAD_AUTO_FINALIZE_SOFT_FAIL', [
                    'squad_session_id' => $session->id,
                    'error'            => $e->getMessage(),
                ]);
            }
            return;
        }

        // Otherwise, at least one resolved → flip to partially_paid so
        // the UI can show progress and host gets the "push now" affordance.
        if ($session->status === 'awaiting_payments'
            && $session->payments()->whereIn('status', [
                SquadPayment::STATUS_PAID,
                SquadPayment::STATUS_FAILED,
                SquadPayment::STATUS_SKIPPED,
            ])->exists()
        ) {
            $session->update(['status' => 'partially_paid']);
        }
    }

    private function refundPayment(SquadPayment $payment, string $reason): void
    {
        if (! $payment->isPaid()) {
            return;
        }
        $shareMajor = number_format($payment->share_piasters / 100, 2, '.', '');
        $refundTxId = (string) Str::uuid();

        $result = $this->mastercard->refund(
            gatewayOrderId: $payment->gateway_order_id,
            transactionId:  $refundTxId,
            amount:         $shareMajor,
            currency:       'EGP',
        );

        if ($result['success']) {
            $payment->update([
                'status'                => SquadPayment::STATUS_REFUNDED,
                'refund_transaction_id' => $result['gateway_reference'] ?? $refundTxId,
                'refunded_at'           => now(),
                'failed_reason'         => $reason,
            ]);
        } else {
            Log::error('SQUAD_REFUND_FAILED', [
                'squad_payment_id' => $payment->id,
                'reason'           => $reason,
                'error'            => $result['message'] ?? ($result['error'] ?? 'refund_failed'),
            ]);
            // Don't flip to refunded — leaves the row in 'paid' so ops can
            // see the orphan and settle it manually. Refund failure is
            // tracked separately in failed_reason.
            $payment->update(['failed_reason' => 'refund_failed: ' . ($result['message'] ?? '')]);
        }
    }

    private function broadcastPaymentUpdate(SquadSession $session, SquadPayment $payment): void
    {
        broadcast(new SquadEvent($session->id, 'SquadPaymentUpdated', [
            'payment_id'     => $payment->id,
            'member_id'      => $payment->squad_member_id,
            'status'         => $payment->status,
            'share_piasters' => $payment->share_piasters,
            'paid_at'        => optional($payment->paid_at)->toIso8601String(),
            'failed_reason'  => $payment->failed_reason,
            'session_status' => $session->status,
        ]));
    }

    private function mintMastercardSession(): string
    {
        // Replicates MastercardHostedSessionController::createSession.
        // Kept inline rather than extracted so the controller's response
        // shape (success/error JSON) doesn't leak into the service layer.
        $merchantId  = config('mastercard.merchant_id');
        $apiUsername = config('mastercard.api_username') ?: $merchantId;
        $apiPassword = config('mastercard.api_password');
        $base        = rtrim(config('mastercard.gateway'), '/');
        $version     = config('mastercard.api_version');

        if (! $apiUsername || ! $apiPassword) {
            throw new \RuntimeException('Mastercard is not configured.');
        }

        $url = "{$base}/api/rest/version/{$version}/merchant/{$merchantId}/session";
        $response = Http::withBasicAuth($apiUsername, $apiPassword)
            ->withHeaders(['Accept' => 'application/json', 'Content-Type' => 'application/json'])
            ->post($url, ['session' => ['authenticationLimit' => 25]]);

        if (! $response->successful()) {
            Log::warning('Mastercard session mint failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException('Could not create payment session.');
        }

        $sessionId = $response->json('session.id');
        if (! $sessionId) {
            throw new \RuntimeException('Payment gateway returned no session id.');
        }
        return $sessionId;
    }

    private function mastercardSessionJsUrl(): string
    {
        $merchantId = config('mastercard.merchant_id');
        $base       = rtrim(config('mastercard.gateway'), '/');
        $version    = config('mastercard.api_version');
        return "{$base}/form/version/{$version}/merchant/{$merchantId}/session.js";
    }

    private function generateGatewayOrderId(): string
    {
        // Human-readable but unique; MPGS reuses it across the 3-step flow.
        return 'KIP-SQ-' . strtoupper(Str::random(12));
    }

    private function guardIsHost(Customer $caller, SquadSession $session): void
    {
        if ($session->host_id !== $caller->id) {
            throw new \DomainException('Only the host can perform this action.');
        }
    }

    private function generateInviteCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (SquadSession::where('invite_code', $code)->exists());

        return $code;
    }

    private function cartPayload(SquadSession $session): array
    {
        $session->load('cartItems.product');
        $items    = $session->cartItems;
        $subtotal = $items->sum(fn ($i) => $i->lineTotal());

        return [
            'items'    => $items->map(fn ($i) => [
                'id'          => $i->id,
                'product_id'  => $i->product_id,
                'name_en'     => $i->product?->getName('en') ?? '',
                'quantity'    => $i->quantity,
                'unit_price'  => $i->unit_price,
                'line_total'  => $i->lineTotal(),
                'member_id'   => $i->squad_member_id,
            ])->toArray(),
            'totals' => [
                'subtotal'   => $subtotal,
                'item_count' => $items->sum('quantity'),
            ],
        ];
    }
}
