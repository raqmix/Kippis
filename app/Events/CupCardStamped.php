<?php

namespace App\Events;

use App\Core\Models\Customer;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A completed order added a stamp to the cup card (push "your next coffee is N% off").
 */
class CupCardStamped implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Customer $customer,
        public int $stamps,
        public float $nextPercent,
        public ?int $orderId = null,
    ) {}
}
