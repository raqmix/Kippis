<?php

namespace App\Services\Rewards;

use App\Core\Models\Admin;
use App\Core\Models\CustomerRedeemWallet;
use Illuminate\Support\Facades\DB;

/**
 * Cashier side of the Rewards wallet: look a gift up by the code the
 * customer shows (typed, scanned, or the QR URL) and mark it used.
 *
 * Any available, unexpired entry can be redeemed here (counter gifts are
 * the main case; a product/free-item gift handed over at the counter is
 * also single-use, so it is closed the same way).
 */
class CounterRedemptionService
{
    public function find(?string $codeOrUrl): ?CustomerRedeemWallet
    {
        $code = CustomerRedeemWallet::normaliseCode($codeOrUrl);
        if ($code === '') {
            return null;
        }

        return CustomerRedeemWallet::query()
            ->where('code', $code)
            ->with(['customer', 'redeemItem', 'usedByAdmin', 'order'])
            ->first();
    }

    /**
     * Why this entry can't be redeemed right now, or null if it can.
     * Returns a lang key under `rewards.`.
     */
    public function blockingReason(CustomerRedeemWallet $entry): ?string
    {
        if ($entry->status === CustomerRedeemWallet::STATUS_APPLIED) {
            return 'already_used';
        }
        if ($entry->displayStatus() === CustomerRedeemWallet::STATUS_EXPIRED) {
            return 'expired';
        }
        if ($entry->status !== CustomerRedeemWallet::STATUS_AVAILABLE) {
            return 'not_usable';
        }

        return null;
    }

    /**
     * @throws \DomainException with a translated message when the gift is
     *                          used, expired or otherwise not redeemable
     */
    public function markUsed(CustomerRedeemWallet $entry, ?Admin $admin): CustomerRedeemWallet
    {
        $result = DB::transaction(function () use ($entry, $admin) {
            /** @var CustomerRedeemWallet $locked */
            $locked = CustomerRedeemWallet::query()->whereKey($entry->getKey())->lockForUpdate()->firstOrFail();

            if ($reason = $this->blockingReason($locked)) {
                if ($reason === 'expired' && $locked->status === CustomerRedeemWallet::STATUS_AVAILABLE) {
                    $locked->update(['status' => CustomerRedeemWallet::STATUS_EXPIRED]);
                }

                return $reason;
            }

            $locked->update([
                'status'           => CustomerRedeemWallet::STATUS_APPLIED,
                'used_at'          => now(),
                'used_by_admin_id' => $admin?->getKey(),
            ]);

            return $locked;
        });

        if (is_string($result)) {
            throw new \DomainException(__('rewards.' . $result));
        }

        return $result->fresh(['customer', 'redeemItem', 'usedByAdmin']);
    }
}
