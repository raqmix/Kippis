<?php

namespace App\Events;

use App\Core\Models\Customer;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The last slot of the cup card was bought: the card is done and a new one starts.
 */
class CupCardCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Customer $customer,
        public ?int $orderId = null,
    ) {}
}
