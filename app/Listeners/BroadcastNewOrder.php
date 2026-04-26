<?php

namespace App\Listeners;

use App\Events\NewOrderPlaced;
use App\Events\OrderCreated;
use Illuminate\Contracts\Queue\ShouldQueue;

class BroadcastNewOrder implements ShouldQueue
{
    public function handle(OrderCreated $event): void
    {
        $event->order->loadMissing('customer');
        NewOrderPlaced::dispatch($event->order);
    }
}
