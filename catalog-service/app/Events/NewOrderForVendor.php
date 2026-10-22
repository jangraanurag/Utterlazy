<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewOrderForVendor implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public $order,
        public $restaurantId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('vendor.'.$this->restaurantId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.new';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'restaurant_id' => $this->restaurantId,
            'total' => $this->order->grand_total,
            'items' => $this->order->items->count(),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
