<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public array $statusData,
        public array $recipientIds,
        public string $recipientType = 'vendor',
    ) {}

    public function broadcastOn(): array
    {
        $channels = [];
        foreach ($this->recipientIds as $id) {
            $channels[] = new PrivateChannel($this->recipientType . '.' . $id);
        }
        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'status.changed';
    }

    public function broadcastWith(): array
    {
        return $this->statusData;
    }
}
