<?php

namespace App\Observers;

use App\Jobs\CheckPricingParity;
use App\Jobs\MirrorOrderToOrderCommand;
use App\Models\Order;
use App\Services\OutboxPublisher;

class OrderObserver
{
    public function __construct(private readonly OutboxPublisher $publisher)
    {
    }

    public function created(Order $order): void
    {
        $this->publisher->enqueue(
            'order',
            $order->id,
            'order.created',
            $this->orderPayload($order),
            $order->city_id ?? $order->restaurant_id,
        );

        $this->mirrorToShadow($order);
        $this->checkPricingParity($order);
    }

    public function updated(Order $order): void
    {
        if ($order->wasChanged('status')) {
            $this->publisher->enqueue(
                'order',
                $order->id,
                'order.status_changed',
                array_merge($this->orderPayload($order), [
                    'from_status' => $order->getOriginal('status'),
                ]),
                $order->city_id ?? $order->restaurant_id,
            );

            $this->mirrorToShadow($order);
        }

        if ($order->wasChanged('payment_status')) {
            $this->publisher->enqueue(
                'payment',
                $order->id,
                'payment.status_changed',
                [
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'restaurant_id' => $order->restaurant_id,
                    'payment_status' => $order->payment_status,
                    'payment_mode' => $order->payment_mode,
                    'razorpay_payment_id' => $order->razorpay_payment_id,
                    'grand_total' => $order->grand_total,
                ],
                $order->city_id ?? $order->restaurant_id,
            );
        }
    }

    private function mirrorToShadow(Order $order): void
    {
        if (config('services.order_command.shadow_enabled')) {
            MirrorOrderToOrderCommand::dispatch($order->id);
        }
    }

    private function checkPricingParity(Order $order): void
    {
        if (config('services.order_command.cutover_enabled')) {
            CheckPricingParity::dispatch($order->id);
        }
    }

    private function orderPayload(Order $order): array
    {
        return [
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'restaurant_id' => $order->restaurant_id,
            'city_id' => $order->city_id,
            'status' => $order->status,
            'channel' => $order->channel ?? 'app',
            'payment_mode' => $order->payment_mode,
            'grand_total' => (float) $order->grand_total,
        ];
    }
}
