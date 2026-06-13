<?php

namespace App\Listeners;

use App\Events\LoyaltyWalletUpdated;
use App\Jobs\PushWalletUpdate;

/**
 * Translates the `LoyaltyWalletUpdated` domain event into a queued
 * fan-out job. Keeping listener thin so the queue worker handles
 * retries/timeouts on the actual APNs + Google PATCH calls.
 */
class PushWalletUpdateListener
{
    public function handle(LoyaltyWalletUpdated $event): void
    {
        PushWalletUpdate::dispatch($event->wallet->id, $event->reason);
    }
}
