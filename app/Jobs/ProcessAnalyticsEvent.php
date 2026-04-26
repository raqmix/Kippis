<?php

namespace App\Jobs;

use App\Core\Models\AnalyticsEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAnalyticsEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly string $eventName,
        public readonly array  $properties,
        public readonly ?int   $customerId,
        public readonly ?int   $storeId,
        public readonly string $platform,
        public readonly string $occurredAt,
        public readonly ?string $sessionId,
    ) {}

    public function handle(): void
    {
        AnalyticsEvent::create([
            'event_name'  => $this->eventName,
            'customer_id' => $this->customerId,
            'store_id'    => $this->storeId,
            'session_id'  => $this->sessionId,
            'platform'    => $this->platform,
            'properties'  => $this->properties,
            'occurred_at' => $this->occurredAt,
        ]);

        // Phase 3: forward to external pipeline (BigQuery, Mixpanel, etc.)
    }
}
