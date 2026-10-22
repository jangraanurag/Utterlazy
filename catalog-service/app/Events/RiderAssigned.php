<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RiderAssigned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public $order,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('order.'.$this->order->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'rider.assigned';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'rider_id' => $this->order->rider_id,
            'rider_name' => $this->order->rider?->name ?? 'Rider',
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
