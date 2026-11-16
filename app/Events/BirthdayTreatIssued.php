<?php

namespace App\Events;

use App\Core\Models\Customer;
use App\Core\Models\CustomerRedeemWallet;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once when a birthday treat is newly put in a customer's Rewards
 * wallet (not on an idempotent re-issue). Notifications listen to this.
 */
class BirthdayTreatIssued
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Customer $customer,
        public readonly CustomerRedeemWallet $reward,
    ) {
    }
}
