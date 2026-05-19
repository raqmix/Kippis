<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Jobs\PushOrderToFoodics;

/**
 * Bridges OrderCreated to the async Foodics push job.
 * Kept thin on purpose: all skip logic + payload + retry policy lives in the
 * job, so a future re-queue path (e.g. Filament retry button) reuses it.
 */
class PushOrderToFoodicsListener
{
    public function handle(OrderCreated $event): void
    {
        PushOrderToFoodics::dispatch($event->order->id);
    }
}
