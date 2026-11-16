<?php

namespace App\Observers;

use App\Core\Models\Order;
use App\Core\Repositories\LoyaltyWalletRepository;
use App\Events\OrderStatusUpdated;
use App\Services\Loyalty\PointsEarning;
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

        // Cancelled before completion: give the gift back (single use
        // means "used on an order that went through").
        if ($order->status === 'cancelled' && $oldStatus !== 'cancelled' && $oldStatus !== 'completed') {
            $this->restoreWalletItem($order);
            return;
        }

        // Only process customer orders that just became completed
        if ($order->status !== 'completed' || $oldStatus === 'completed') {
            return;
        }

        if (!$order->customer_id) {
            return;
        }

        // Rate: `loyalty.egp_per_point` when set (floor), else the legacy
        // `loyalty.points_per_order_egp`; app orders may get a double-points
        // rule on top (see PointsEarning).
        $earning = app(PointsEarning::class)->calculate($order);
        $points = $earning['points'];
        $description = "Points from order #{$order->id}";
        if ($earning['rule']) {
            $description .= ' (x' . rtrim(rtrim(number_format($earning['multiplier'], 2, '.', ''), '0'), '.')
                . ' ' . $earning['rule']->name . ')';
        }

        if ($points > 0) {
            try {
                $wallet = $this->loyaltyWalletRepository->getOrCreateForCustomer($order->customer_id);
                $this->loyaltyWalletRepository->addPoints(
                    $wallet,
                    $points,
                    'earned',
                    $description,
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

    /**
     * Put the rewards-wallet entry this order consumed back to available.
     * Only touches the entry if it is still linked to this order, so a
     * restore can't revive a gift that has since been used elsewhere.
     */
    private function restoreWalletItem(Order $order): void
    {
        if (! $order->wallet_item_id) {
            return;
        }

        try {
            \App\Core\Models\CustomerRedeemWallet::query()
                ->whereKey($order->wallet_item_id)
                ->where('status', \App\Core\Models\CustomerRedeemWallet::STATUS_APPLIED)
                ->where('used_order_id', $order->id)
                ->update([
                    'status'        => \App\Core\Models\CustomerRedeemWallet::STATUS_AVAILABLE,
                    'used_order_id' => null,
                    'used_at'       => null,
                    'updated_at'    => now(),
                ]);
        } catch (\Throwable $e) {
            Log::error('Failed to restore wallet item on cancelled order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
