<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RiderAssigned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public array $assignmentData,
        public int $riderId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('rider.' . $this->riderId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'rider.assigned';
    }

    public function broadcastWith(): array
    {
        return $this->assignmentData;
    }
}
