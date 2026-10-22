<?php

namespace App\Events;

use App\Models\RiderAssignment;
use App\Services\RiderAssignmentService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewAssignmentForRider implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public RiderAssignment $assignment,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('rider.'.$this->assignment->rider_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'assignment.new';
    }

    public function broadcastWith(): array
    {
        $order = $this->assignment->order;
        $rider = $this->assignment->rider;
        $eta = RiderAssignmentService::distanceEtaPackage($order, $rider);

        return array_merge([
            'assignment_id' => $this->assignment->id,
            'order_id' => $order->id,
            'restaurant_name' => $order->restaurant?->name ?? 'Restaurant',
            'restaurant_address' => $order->restaurant?->address ?? '',
            'distance_km' => $this->assignment->distance_km,
            'grand_total' => $order->grand_total,
            'item_count' => $order->items->count(),
            'pickup_address' => $order->restaurant?->address ?? '',
            'delivery_address' => $order->delivery_address ?? '',
            'timestamp' => now()->toIso8601String(),
        ], $eta);
    }
}
