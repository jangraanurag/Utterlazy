<?php

namespace App\Listeners;

use App\Events\NewOrderForVendor;
use App\Services\FcmService;

class SendVendorNewOrderNotification
{
    public function __construct(private FcmService $fcm) {}

    public function handle(NewOrderForVendor $event): void
    {
        $order = $event->order;
        $restaurant = $order->restaurant ?? null;

        if (! $restaurant) {
            return;
        }

        $total = number_format((float) ($order->grand_total ?? 0), 0);

        $this->fcm->sendToRestaurant(
            $restaurant,
            [
                'event' => 'new_order',
                'order_id' => (string) $order->id,
                'grand_total' => (string) $order->grand_total,
                'screen' => 'incoming_order',
            ],
            'New Order Received',
            "Order #{$order->id} — ₹{$total}"
        );
    }
}
