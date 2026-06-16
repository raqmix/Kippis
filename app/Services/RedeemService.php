<?php

namespace App\Services;

use App\Core\Models\Customer;
use App\Core\Models\CustomerRedeemWallet;
use App\Core\Models\RedeemItem;
use App\Core\Models\Setting;
use App\Core\Repositories\LoyaltyWalletRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RedeemService
{
    public function __construct(private LoyaltyWalletRepository $loyaltyRepo)
    {
    }

    /**
     * Customer spends points to claim a redeem item. Result is a row
     * in customer_redeem_wallet they apply at cart later.
     *
     * Locked per (customer, item) so a double-tap can't double-deduct.
     * All caps are checked AFTER the lock for race safety.
     */
    public function claim(Customer $customer, RedeemItem $item, ?int $storeId = null): CustomerRedeemWallet
    {
        if (! (bool) Setting::get('loyalty.redemption_enabled', true)) {
            throw new \DomainException('Redemption is currently disabled.');
        }

        $lock = Cache::lock("redeem_claim:{$customer->id}:{$item->id}", 30);
        if (! $lock->get()) {
            throw new \DomainException('Another claim is in flight — try again.');
        }

        try {
            $item->loadMissing('stores');

            if (! $item->is_active) {
                throw new \DomainException('This reward is no longer available.');
            }

            // Per-branch eligibility check matches the AvailableAt scope.
            if ($storeId !== null && $item->stores->isNotEmpty()
                && ! $item->stores->contains('id', $storeId)) {
                throw new \DomainException('This reward is not available at this branch.');
            }

            $this->enforceCaps($customer, $item);

            $wallet = $this->loyaltyRepo->getOrCreateForCustomer($customer->id);

            return DB::transaction(function () use ($customer, $item, $wallet) {
                // deductPoints throws DomainException on insufficient
                // balance — we let that bubble up, the controller
                // surfaces it to the client as 422.
                $this->loyaltyRepo->deductPoints(
                    $wallet,
                    $item->points_cost,
                    'redeemed',
                    'Redeemed: ' . $item->getTitle('en'),
                    'redeem_item',
                    $item->id,
                );

                $ttl = $item->wallet_ttl_days
                    ?? (int) Setting::get('loyalty.wallet_item_ttl_days', 0);

                return CustomerRedeemWallet::create([
                    'customer_id'         => $customer->id,
                    'redeem_item_id'      => $item->id,
                    'points_spent'        => $item->points_cost,
                    'status'              => CustomerRedeemWallet::STATUS_AVAILABLE,
                    'expires_at'          => $ttl > 0 ? now()->addDays($ttl) : null,
                    'title_snapshot_json' => $item->title_json,
                ]);
            });
        } finally {
            $lock->release();
        }
    }

    /**
     * Pre-flight cap check. Throws with a specific message so the UI
     * can tell the customer exactly why they can't claim right now.
     */
    private function enforceCaps(Customer $customer, RedeemItem $item): void
    {
        if ($item->max_per_customer_lifetime !== null) {
            $count = CustomerRedeemWallet::query()
                ->where('customer_id', $customer->id)
                ->where('redeem_item_id', $item->id)
                ->whereIn('status', [
                    CustomerRedeemWallet::STATUS_AVAILABLE,
                    CustomerRedeemWallet::STATUS_APPLIED,
                ])
                ->count();
            if ($count >= $item->max_per_customer_lifetime) {
                throw new \DomainException('You\'ve hit the lifetime cap on this reward.');
            }
        }

        if ($item->max_per_customer_per_day !== null) {
            $today = CustomerRedeemWallet::query()
                ->where('customer_id', $customer->id)
                ->where('redeem_item_id', $item->id)
                ->whereDate('created_at', now()->toDateString())
                ->count();
            if ($today >= $item->max_per_customer_per_day) {
                throw new \DomainException('You\'ve hit today\'s cap on this reward — try again tomorrow.');
            }
        }

        if ($item->max_global !== null) {
            $globalActive = CustomerRedeemWallet::query()
                ->where('redeem_item_id', $item->id)
                ->whereIn('status', [
                    CustomerRedeemWallet::STATUS_AVAILABLE,
                    CustomerRedeemWallet::STATUS_APPLIED,
                ])
                ->count();
            if ($globalActive >= $item->max_global) {
                throw new \DomainException('This reward is sold out — check back later.');
            }
        }
    }

    /** Available redeem items at a branch (or globally if storeId is null). */
    public function availableItems(?int $storeId = null)
    {
        return RedeemItem::query()
            ->availableAt($storeId)
            ->with(['product', 'stores'])
            ->orderBy('sort_order')
            ->get();
    }

    /** Customer's claimed wallet items in `available` status. */
    public function walletForCustomer(Customer $customer)
    {
        $this->sweepExpired($customer);

        return CustomerRedeemWallet::query()
            ->where('customer_id', $customer->id)
            ->where('status', CustomerRedeemWallet::STATUS_AVAILABLE)
            ->with('redeemItem')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Expire stale wallet items. Cheap to run inline on each fetch —
     * upper-bound is the customer's claim history, not the global table.
     */
    private function sweepExpired(Customer $customer): void
    {
        CustomerRedeemWallet::query()
            ->where('customer_id', $customer->id)
            ->where('status', CustomerRedeemWallet::STATUS_AVAILABLE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => CustomerRedeemWallet::STATUS_EXPIRED]);
    }
}
