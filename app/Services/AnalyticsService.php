<?php

namespace App\Services;

use App\Core\Models\Customer;
use App\Core\Models\Store;
use App\Jobs\ProcessAnalyticsEvent;

class AnalyticsService
{
    /**
     * Track an analytics event by dispatching to the queue.
     *
     * @param string        $event      Event name e.g. 'order.placed', 'product.viewed'
     * @param array         $properties Event-specific payload
     * @param Customer|null $customer   Associated customer (if any)
     * @param Store|null    $store      Associated store (if any)
     * @param string        $platform   'web' | 'mobile' | 'kiosk' | 'admin'
     * @param string|null   $sessionId  Optional session/request identifier
     */
    public function track(
        string    $event,
        array     $properties = [],
        ?Customer $customer = null,
        ?Store    $store = null,
        string    $platform = 'web',
        ?string   $sessionId = null,
    ): void {
        ProcessAnalyticsEvent::dispatch(
            eventName:   $event,
            properties:  $properties,
            customerId:  $customer?->id,
            storeId:     $store?->id,
            platform:    $platform,
            occurredAt:  now()->toDateTimeString(),
            sessionId:   $sessionId,
        );
    }
}
