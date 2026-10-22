<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RequiresRestaurantApproval implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $entityType,
        public string $entityId,
        public string $restaurantId,
        public string $submittedBy,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('restaurant.'.$this->restaurantId.'.approvals'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'approval.pending';
    }

    public function broadcastWith(): array
    {
        return [
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'restaurant_id' => $this->restaurantId,
            'submitted_by' => $this->submittedBy,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
