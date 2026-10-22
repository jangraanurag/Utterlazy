<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Services\FcmService;

class SendCustomerOrderStatusNotification
{
    public function __construct(private FcmService $fcm) {}

    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order;
        $customer = $order->user ?? null;

        if (! $customer) {
            return;
        }

        $status = $event->newStatus;

        $statusMessages = [
            'confirmed' => ['Order Confirmed', 'Your order has been confirmed by the restaurant.'],
            'preparing' => ['Being Prepared', 'Your food is being prepared with love.'],
            'ready_for_pickup' => ['Food is Ready', 'Your order is ready for pickup.'],
            'out_for_delivery' => ['On the Way', 'Your order is on its way to you!'.($order->delivery_otp ? " Use OTP: {$order->delivery_otp} to receive it." : '')],
            'delivered' => ['Delivered', 'Your order has been delivered. Enjoy your meal!'],
            'cancelled' => ['Order Cancelled', 'Your order has been cancelled.'],
        ];

        [$title, $body] = $statusMessages[$status] ?? ['Order Update', "Order status: $status"];

        $this->fcm->sendToUser(
            $customer,
            'customer',
            [
                'type' => 'order_status',
                'order_id' => (string) $order->id,
                'status' => $status,
                'screen' => 'order_tracking',
            ],
            $title,
            $body
        );
    }
}
