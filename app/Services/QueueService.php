<?php

namespace App\Services;

use App\Core\Enums\ActivityAction;
use App\Core\Models\Admin;
use App\Core\Models\Order;
use App\Core\Models\Store;
use App\Core\Services\ActivityLogService;
use App\Events\OrderStatusUpdated;
use Illuminate\Support\Collection;

class QueueService
{
    // Valid status transitions in the queue flow
    private const TRANSITIONS = [
        'confirmed'  => 'preparing',
        'preparing'  => 'ready',
        'ready'      => 'picked_up',
    ];

    // Display priority for column ordering
    private const STATUS_PRIORITY = [
        'confirmed' => 0,
        'preparing' => 1,
        'ready'     => 2,
    ];

    public function __construct(
        private readonly ActivityLogService $activityLog,
    ) {}

    /**
     * Return today's active queue orders grouped by status.
     */
    public function getStoreQueue(Store $store): Collection
    {
        return Order::query()
            ->where('store_id', $store->id)
            ->whereIn('status', ['confirmed', 'preparing', 'ready'])
            ->whereDate('created_at', today())
            ->orderByRaw("CASE status
                WHEN 'confirmed' THEN 0
                WHEN 'preparing' THEN 1
                WHEN 'ready'     THEN 2
                ELSE 3
            END")
            ->orderBy('created_at')
            ->get()
            ->groupBy('status');
    }

    /**
     * Transition an order to the next queue status.
     *
     * @throws \DomainException if the transition is not allowed
     */
    public function transitionOrder(Order $order, string $newStatus, Admin $admin): void
    {
        $allowedNext = self::TRANSITIONS[$order->status] ?? null;

        if ($allowedNext !== $newStatus) {
            throw new \DomainException(
                "Cannot transition order from '{$order->status}' to '{$newStatus}'. "
                . "Allowed next status: " . ($allowedNext ?? '(none)') . "."
            );
        }

        $oldStatus = $order->status;
        $order->update(['status' => $newStatus]);

        // Broadcast to queue screen and to the customer app
        OrderStatusUpdated::dispatch($order, $oldStatus);

        $this->activityLog->log(
            ActivityAction::UPDATE,
            $order,
            ['status' => $oldStatus],
            ['status' => $newStatus],
            $admin->id,
        );
    }
}
