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
use Illuminate\Support\Str;

class RefundService
{
    public function __construct(
        private readonly MastercardPaymentService $mastercard,
        private readonly LoyaltyWalletRepository $loyaltyRepo,
        private readonly ActivityLogService $activityLog,
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
                $this->piasersToDecimalString((int) ($order->total * 100)),
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
            'amount'            => (int) ($order->total * 100),
            'reason'            => $reason,
            'status'            => $status,
            'gateway_reference' => $gatewayReference,
            'gateway_response'  => $gatewayResponse,
            'processed_at'      => $status === 'completed' ? now() : null,
        ]);

        if ($status === 'completed') {
            $order->update(['status' => 'voided', 'refund_status' => 'voided']);
            $this->deductLoyaltyPoints($order);

            PaymentTransaction::create([
                'order_id'          => $order->id,
                'type'              => 'void',
                'amount'            => (int) ($order->total * 100),
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

        return $this->issueRefund($order, $admin, 'full', (int) ($order->total * 100), $reason);
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

        $totalPiasters = (int) ($order->total * 100);
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
                $this->piasersToDecimalString($amountPiasters),
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
            $totalPiasters = (int) ($order->total * 100);
            $newRefunded   = $order->refunded_amount + $amountPiasters;
            $newStatus     = $newRefunded >= $totalPiasters ? 'full' : 'partial';

            $order->update([
                'refund_status'   => $newStatus,
                'refunded_amount' => $newRefunded,
            ]);

            if ($type === 'full') {
                $this->deductLoyaltyPoints($order);
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
            $pointsPerEgp = (int) config('core.loyalty.points_per_order_egp', 1);
            $points = (int) round($order->total * $pointsPerEgp);

            if ($points > 0) {
                $this->loyaltyRepo->deductPoints(
                    $wallet,
                    $points,
                    'deducted',
                    "Points reversed for refunded order #{$order->id}",
                    'order',
                    $order->id,
                );
            }
        } catch (\Exception) {
            // Non-fatal: loyalty deduction failure should not block the refund
        }
    }

    private function piasersToDecimalString(int $piasters): string
    {
        return number_format($piasters / 100, 2, '.', '');
    }
}
