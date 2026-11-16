<?php

namespace App\Services;

use App\Core\Enums\ActivityAction;
use App\Core\Models\Admin;
use App\Core\Models\Order;
use App\Core\Models\PaymentTransaction;
use App\Core\Models\Refund;
use App\Core\Repositories\LoyaltyWalletRepository;
use App\Core\Services\ActivityLogService;
use App\Services\MastercardPaymentService;
use App\Services\SpendRewardService;
use App\Support\Money;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RefundService
{
    public function __construct(
        private readonly MastercardPaymentService $mastercard,
        private readonly LoyaltyWalletRepository $loyaltyRepo,
        private readonly ActivityLogService $activityLog,
        private readonly SpendRewardService $spendRewardService,
    ) {}

    /**
     * Void an order that has not yet been fulfilled.
     * Allowed statuses: 'received', 'pending_payment'
     */
    public function void(Order $order, Admin $admin, string $reason): Refund
    {
        if (! in_array($order->status, ['received', 'pending_payment'], true)) {
            throw new \DomainException('Order cannot be voided in its current status.');
        }

        if ($order->refund_status !== 'none') {
            throw new \DomainException('Order has already been refunded or voided.');
        }

        $gatewayReference = null;
        $gatewayResponse  = null;
        $status           = 'completed';

        // Attempt gateway void/refund if a payment was captured
        if ($order->gateway_order_id && $order->payment_method !== 'cash') {
            $result = $this->mastercard->refund(
                $order->gateway_order_id,
                'VOID-' . Str::upper(Str::random(8)),
                Money::piastersToDecimalString(Money::toPiasters((float) $order->total)),
                'EGP',
            );

            if (! $result['success']) {
                $status = 'failed';
            }

            $gatewayReference = $result['gateway_reference'] ?? null;
            $gatewayResponse  = $result['raw_response'] ?? null;
        }

        $refund = Refund::create([
            'order_id'          => $order->id,
            'admin_id'          => $admin->id,
            'type'              => 'void',
            'amount'            => Money::toPiasters((float) $order->total),
            'reason'            => $reason,
            'status'            => $status,
            'gateway_reference' => $gatewayReference,
            'gateway_response'  => $gatewayResponse,
            'processed_at'      => $status === 'completed' ? now() : null,
        ]);

        if ($status === 'completed') {
            $order->update(['status' => 'voided', 'refund_status' => 'voided']);
            // No loyalty deduction here: void() only fires on `received` /
            // `pending_payment`, statuses for which OrderObserver never
            // awarded points. Calling deductLoyaltyPoints() would drive the
            // wallet negative against unrelated past earnings (#25).

            PaymentTransaction::create([
                'order_id'          => $order->id,
                'type'              => 'void',
                'amount'            => Money::toPiasters((float) $order->total),
                'gateway'           => $order->payment_method === 'cash' ? 'cash' : 'mastercard',
                'gateway_reference' => $gatewayReference,
                'gateway_status'    => 'voided',
                'gateway_response'  => $gatewayResponse,
            ]);
        }

        $this->activityLog->log(ActivityAction::UPDATE, $order, ['status' => $order->getOriginal('status')], ['status' => 'voided', 'refund_id' => $refund->id], $admin->id);

        return $refund;
    }

    /**
     * Full refund of a completed order.
     */
    public function refundFull(Order $order, Admin $admin, string $reason): Refund
    {
        if ($order->status !== 'completed') {
            throw new \DomainException('Only completed orders can be refunded.');
        }

        if ($order->refund_status !== 'none') {
            throw new \DomainException('Order has already been refunded.');
        }

        return $this->issueRefund($order, $admin, 'full', Money::toPiasters((float) $order->total), $reason);
    }

    /**
     * Partial refund of a completed order.
     *
     * @param int $amountPiasters Amount to refund in piasters
     */
    public function refundPartial(Order $order, Admin $admin, int $amountPiasters, string $reason): Refund
    {
        if ($order->status !== 'completed') {
            throw new \DomainException('Only completed orders can be refunded.');
        }

        $totalPiasters = Money::toPiasters((float) $order->total);
        $alreadyRefunded = $order->refunded_amount;
        $available = $totalPiasters - $alreadyRefunded;

        if ($amountPiasters <= 0 || $amountPiasters > $available) {
            throw new \DomainException("Refund amount must be between 1 and {$available} piasters.");
        }

        return $this->issueRefund($order, $admin, 'partial', $amountPiasters, $reason);
    }

    private function issueRefund(Order $order, Admin $admin, string $type, int $amountPiasters, string $reason): Refund
    {
        $gatewayReference = null;
        $gatewayResponse  = null;
        $status           = 'completed';

        if ($order->gateway_order_id && $order->payment_method !== 'cash') {
            $result = $this->mastercard->refund(
                $order->gateway_order_id,
                'REFUND-' . Str::upper(Str::random(8)),
                Money::piastersToDecimalString($amountPiasters),
                'EGP',
            );

            if (! $result['success']) {
                $status = 'failed';
            }

            $gatewayReference = $result['gateway_reference'] ?? null;
            $gatewayResponse  = $result['raw_response'] ?? null;
        }

        $refund = Refund::create([
            'order_id'          => $order->id,
            'admin_id'          => $admin->id,
            'type'              => $type,
            'amount'            => $amountPiasters,
            'reason'            => $reason,
            'status'            => $status,
            'gateway_reference' => $gatewayReference,
            'gateway_response'  => $gatewayResponse,
            'processed_at'      => $status === 'completed' ? now() : null,
        ]);

        if ($status === 'completed') {
            $totalPiasters = Money::toPiasters((float) $order->total);
            $newRefunded   = $order->refunded_amount + $amountPiasters;
            $newStatus     = $newRefunded >= $totalPiasters ? 'full' : 'partial';

            $order->update([
                'refund_status'   => $newStatus,
                'refunded_amount' => $newRefunded,
            ]);

            if ($type === 'full') {
                $this->deductLoyaltyPoints($order);
                // Reverse the completed-order spend from the customer's
                // lifetime counter. Vouchers already issued from that
                // spend are NOT auto-revoked (per-tier revocation
                // policy is a future extension); we just clamp the
                // counter so future crossings recompute correctly.
                try {
                    $this->spendRewardService->reverseSpend($order);
                } catch (\Throwable $e) {
                    Log::error('Failed to reverse spend for milestone rewards', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            PaymentTransaction::create([
                'order_id'          => $order->id,
                'type'              => 'refund',
                'amount'            => $amountPiasters,
                'gateway'           => $order->payment_method === 'cash' ? 'cash' : 'mastercard',
                'gateway_reference' => $gatewayReference,
                'gateway_status'    => 'refunded',
                'gateway_response'  => $gatewayResponse,
            ]);
        }

        $this->activityLog->log(ActivityAction::UPDATE, $order, ['refund_status' => $order->getOriginal('refund_status')], ['refund_status' => $order->fresh()->refund_status, 'refund_id' => $refund->id], $admin->id);

        return $refund;
    }

    private function deductLoyaltyPoints(Order $order): void
    {
        if (! $order->customer_id) {
            return;
        }

        try {
            $wallet = $this->loyaltyRepo->getOrCreateForCustomer($order->customer_id);
            $points = $this->pointsToReverse($wallet, $order);

            if ($points > 0) {
                $this->loyaltyRepo->deductPoints(
                    $wallet,
                    $points,
                    \App\Core\Models\LoyaltyTransaction::TYPE_DEDUCTED,
                    "Points reversed for refunded order #{$order->id}",
                    'order',
                    $order->id,
                );
            }
        } catch (\Exception) {
            // Non-fatal: loyalty deduction failure should not block the refund
        }
    }

    /**
     * Reverse exactly what the order earned according to the ledger (the
     * earn may have carried a double-points multiplier, used
     * `loyalty.egp_per_point` or the legacy rate), not a re-computation
     * from today's settings. An earn posted before a loyalty:rescale is
     * converted to the current scale (floor: the customer kept the ceil
     * of their balance, so reversing less can never take more than the
     * order is worth today). Anything already reversed is not taken twice.
     */
    private function pointsToReverse(\App\Core\Models\LoyaltyWallet $wallet, Order $order): int
    {
        $windowStart = app(\App\Services\Loyalty\PointsExpiry::class)->windowStart($wallet);
        $factor = max(1, (int) \App\Core\Models\Setting::get('loyalty.rescaled_factor', 1));

        $rows = \App\Core\Models\LoyaltyTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('reference_type', 'order')
            ->where('reference_id', $order->id)
            ->whereIn('type', [
                \App\Core\Models\LoyaltyTransaction::TYPE_EARNED,
                \App\Core\Models\LoyaltyTransaction::TYPE_DEDUCTED,
            ])
            ->get(['id', 'points']);

        $old = $rows->where('id', '<=', $windowStart);
        $new = $rows->where('id', '>', $windowStart);
        $oldNet = max(0, (int) $old->sum('points'));

        return max(0, (int) floor($oldNet / $factor) + (int) $new->sum('points'));
    }
}
