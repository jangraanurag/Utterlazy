<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdminStatsUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public array $stats
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('admin-dashboard'),
        ];
    }

    public function broadcastWith(): array
    {
        return ['stats' => $this->stats];
    }

    public function broadcastAs(): string
    {
        return 'stats.updated';
    }
}
