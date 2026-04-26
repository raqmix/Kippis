<?php

namespace App\Events;

use App\Core\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order,
        public string $oldStatus,
    ) {}

    /**
     * Broadcast on the customer's private channel and the store queue channel.
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("queue.{$this->order->store_id}"),
        ];

        if ($this->order->customer_id) {
            $channels[] = new PrivateChannel("orders.{$this->order->customer_id}");
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'order_id'   => $this->order->id,
            'pos_code'   => $this->order->pos_code,
            'old_status' => $this->oldStatus,
            'new_status' => $this->order->status,
        ];
    }

    public function broadcastAs(): string
    {
        return 'OrderStatusUpdated';
    }
}
