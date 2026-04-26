<?php

namespace App\Events;

use App\Core\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewOrderPlaced implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("queue.{$this->order->store_id}"),
        ];
    }

    public function broadcastWith(): array
    {
        $snapshot = $this->order->items_snapshot ?? [];

        return [
            'order_id'      => $this->order->id,
            'pos_code'      => $this->order->pos_code,
            'items_summary' => collect($snapshot)->map(fn ($item) => [
                'name'     => $item['name_en'] ?? $item['name'] ?? 'Item',
                'quantity' => $item['quantity'] ?? 1,
            ])->values()->all(),
            'customer_name' => $this->order->customer?->name ?? 'Guest',
            'total'         => $this->order->total,
        ];
    }

    public function broadcastAs(): string
    {
        return 'NewOrderPlaced';
    }
}
