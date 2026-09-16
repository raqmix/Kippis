<?php

namespace App\Observers;

use App\Core\Models\Order;
use App\Core\Repositories\LoyaltyWalletRepository;
use App\Events\OrderStatusUpdated;
use App\Services\SpendRewardService;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    public function __construct(
        private LoyaltyWalletRepository $loyaltyWalletRepository,
        private SpendRewardService $spendRewardService,
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

        // Admin-editable via Filament → Loyalty Settings
        // (`loyalty.points_per_order_egp`); env is the fresh-install fallback.
        $pointsPerEgp = (int) \App\Core\Models\Setting::get(
            'loyalty.points_per_order_egp',
            (int) config('core.loyalty.points_per_order_egp', 1),
        );
        $points = (int) round((float) $order->total * $pointsPerEgp);

        if ($points > 0) {
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

        // Spend-milestone counter — increment the customer's lifetime
        // spend and issue any newly-crossed tier vouchers. Isolated
        // from the loyalty path so a failure here doesn't rollback the
        // points award and vice versa.
        try {
            $this->spendRewardService->recordSpend($order);
        } catch (\Throwable $e) {
            Log::error('Failed to record spend for milestone rewards', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        // If the order was fulfilled off a cart that had an applied
        // spend-reward voucher, mark that voucher redeemed. Uses the
        // items_snapshot's `spend_reward_id` marker (added by the
        // service when applying to cart).
        $rewardIds = collect($order->items_snapshot ?? [])
            ->pluck('spend_reward_id')
            ->filter()
            ->unique()
            ->values();
        foreach ($rewardIds as $rewardId) {
            $reward = \App\Core\Models\CustomerSpendReward::find($rewardId);
            if ($reward && $reward->status === \App\Core\Models\CustomerSpendReward::STATUS_APPLIED) {
                $this->spendRewardService->markRedeemed($reward, $order);
            }
        }
    }
}
