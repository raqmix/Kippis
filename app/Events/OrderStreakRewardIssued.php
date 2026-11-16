<?php

namespace App\Events;

use App\Core\Models\Customer;
use App\Core\Models\CustomerRedeemWallet;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Order streak completed: the free coffee was issued and the streak reset.
 */
class OrderStreakRewardIssued implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Customer $customer,
        public CustomerRedeemWallet $reward,
    ) {}
}
