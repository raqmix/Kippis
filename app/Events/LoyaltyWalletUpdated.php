<?php

namespace App\Events;

use App\Core\Models\LoyaltyWallet;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fires every time a wallet's points balance changes. Listener
 * dispatches a queued APNs push (Apple) and a LoyaltyObject.patch
 * (Google) so installed wallet passes update within the 60s SLA.
 */
class LoyaltyWalletUpdated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly LoyaltyWallet $wallet,
        public readonly string $reason, // 'earned' | 'redeemed' | 'expired' | 'refund' | 'manual'
    ) {
    }
}
