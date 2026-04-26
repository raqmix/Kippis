<?php

namespace App\Observers;

use App\Core\Models\Order;
use App\Core\Repositories\LoyaltyWalletRepository;
use App\Events\OrderStatusUpdated;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    public function __construct(
        private LoyaltyWalletRepository $loyaltyWalletRepository
    ) {
    }

    /**
     * Broadcast status change and award loyalty points when an order is completed.
     */
    public function updated(Order $order): void
    {
        $oldStatus = $order->getOriginal('status');

        // Broadcast status change whenever status transitions
        if ($order->isDirty('status') && $oldStatus !== $order->status) {
            OrderStatusUpdated::dispatch($order, $oldStatus);
        }

        // Only process customer orders that just became completed
        if ($order->status !== 'completed' || $oldStatus === 'completed') {
            return;
        }

        if (!$order->customer_id) {
            return;
        }

        $pointsPerEgp = (int) config('core.loyalty.points_per_order_egp', 1);
        $points = (int) round((float) $order->total * $pointsPerEgp);

        if ($points <= 0) {
            return;
        }

        try {
            $wallet = $this->loyaltyWalletRepository->getOrCreateForCustomer($order->customer_id);
            $this->loyaltyWalletRepository->addPoints(
                $wallet,
                $points,
                'earned',
                "Points from order #{$order->id}",
                'order',
                $order->id
            );
        } catch (\Exception $e) {
            Log::error('Failed to award order completion loyalty points', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
