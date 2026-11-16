<?php

namespace App\Events;

use App\Core\Models\Customer;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Bring a Friend (free_coffee mode): the invitee placed their first app order and both got a free coffee.
 */
class ReferralRewardIssued implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Customer $inviter,
        public Customer $invitee,
    ) {}
}
