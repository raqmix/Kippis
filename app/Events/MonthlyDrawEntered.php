<?php

namespace App\Events;

use App\Core\Models\Customer;
use App\Core\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A completed app order is one entry in the Monthly Big Win draw (listener dedupes the push to once per customer per month).
 */
class MonthlyDrawEntered implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Customer $customer,
        public Order $order,
    ) {}
}
