<?php

namespace App\Listeners;

use App\Events\RiderAssigned;
use App\Services\FcmService;

class SendRiderAssignmentNotification
{
    public function __construct(private FcmService $fcm) {}

    public function handle(RiderAssigned $event): void
    {
        $order = $event->order;

        // Notify rider
        $rider = $order->rider ?? null;
        if ($rider) {
            $restaurantName = $order->restaurant?->name ?? 'Restaurant';

            $payout = $order->delivery_fee ?? (45.0 + (($order->grand_total ?? 0) * 0.05));

            $this->fcm->sendToUser(
                $rider,
                'rider',
                [
                    'event' => 'rider_assigned',
                    'order_id' => (string) $order->id,
                    'delivery_fee' => (string) $payout,
                    'screen' => 'assignment_detail',
                ],
                'New Delivery Assignment',
                "Pickup from {$restaurantName}"
            );
        }

        // Notify customer
        $customer = $order->user ?? null;
        if ($customer) {
            $this->fcm->sendToUser(
                $customer,
                'customer',
                [
                    'type' => 'rider_assigned',
                    'order_id' => (string) $order->id,
                    'screen' => 'order_tracking',
                ],
                'Rider Assigned',
                'Your order is on its way!'
            );
        }
    }
}
