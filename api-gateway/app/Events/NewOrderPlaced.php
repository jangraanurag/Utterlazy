<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewOrderPlaced
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public array $orderData,
        public int $vendorId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('vendor.' . $this->vendorId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.new';
    }

    public function broadcastWith(): array
    {
        return $this->orderData;
    }
}
