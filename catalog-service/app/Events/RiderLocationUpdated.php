<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RiderLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public $rider,
        public $latitude,
        public $longitude,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('rider.'.$this->rider->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'location.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'rider_id' => $this->rider->id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
