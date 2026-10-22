<?php

namespace App\Console\Commands;

use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Services\OrderReversalService;
use App\Services\OutboxPublisher;
use Illuminate\Console\Command;

class NoRiderSweep extends Command
{
    protected $signature = 'orders:no-rider-sweep';

    protected $description = 'Alert at 8 minutes with no rider, cancel + reverse at 15 minutes (never punish the customer)';

    public function handle(OutboxPublisher $publisher): int
    {
        $alertAt = now()->subMinutes(8);
        $cancelAt = now()->subMinutes(15);

        $alerts = Order::where('status', 'ready_for_pickup')
            ->whereNull('rider_id')
            ->whereNotNull('ready_marked_at')
            ->where('ready_marked_at', '<=', $alertAt)
            ->where('ready_marked_at', '>', $cancelAt)
            ->get();

        foreach ($alerts as $order) {
            $publisher->enqueue('order', $order->id, 'order.no_rider_alert', [
                'order_id' => $order->id,
                'restaurant_id' => $order->restaurant_id,
                'minutes_waiting' => optional($order->ready_marked_at)->diffInMinutes(now()),
            ], $order->restaurant_id);
        }

        $stuck = Order::where('status', 'ready_for_pickup')
            ->whereNull('rider_id')
            ->whereNotNull('ready_marked_at')
            ->where('ready_marked_at', '<=', $cancelAt)
            ->get();

        $cancelled = 0;
        foreach ($stuck as $order) {
            $order->update([
                'status' => 'cancelled',
                'cancel_reason' => 'no_rider',
            ]);

            OrderReversalService::reverse($order, 'no_rider');

            event(new OrderStatusChanged($order, 'ready_for_pickup', 'cancelled'));
            $cancelled++;
        }

        if ($alerts->count() || $cancelled) {
            $this->info('No-rider sweep: alerts='.$alerts->count().' cancelled='.$cancelled);
        }

        return self::SUCCESS;
    }
}
