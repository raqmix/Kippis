<?php

namespace App\Events;

use App\Core\Models\Customer;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Order streak reached length - 1: one more day earns the free coffee (push "N days in a row!").
 */
class OrderStreakAlmostComplete implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Customer $customer,
        public int $count,
        public int $length,
    ) {}
}
