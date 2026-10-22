<?php

namespace App\Console\Commands;

use App\Events\OrderStatusChanged;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\RiderPayout;
use App\Models\SystemConfig;
use App\Services\OutboxPublisher;
use Illuminate\Console\Command;

class UnreachableSweep extends Command
{
    protected $signature = 'orders:unreachable-sweep';

    protected $description = 'After 5 minutes of an unreachable customer, open a support ticket, abandon the order, pay the rider attempt';

    public function handle(OutboxPublisher $publisher): int
    {
        $cutoff = now()->subMinutes(5);

        $orders = Order::where('status', 'out_for_delivery')
            ->whereNotNull('customer_unreachable_at')
            ->where('customer_unreachable_at', '<=', $cutoff)
            ->get();

        $handled = 0;

        foreach ($orders as $order) {
            $order->update([
                'status' => 'abandoned',
                'cancel_reason' => 'customer_unreachable',
                'cancel_note' => 'Customer unreachable after 3 masked calls and a 5-minute wait.',
            ]);

            Complaint::firstOrCreate(
                ['order_id' => $order->id, 'reason' => 'customer_unreachable'],
                [
                    'user_id' => $order->user_id,
                    'restaurant_id' => $order->restaurant_id,
                    'description' => 'Auto-opened: rider could not reach the customer. Order returned.',
                    'status' => 'pending',
                ],
            );

            $publisher->enqueue('support_ticket', $order->id, 'support.ticket_opened', [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'restaurant_id' => $order->restaurant_id,
                'reason' => 'customer_unreachable',
            ], $order->restaurant_id);

            // Rider is paid for the attempt; not penalised for the customer's absence.
            if ($order->rider_id && ! RiderPayout::where('order_id', $order->id)->exists()) {
                $returnPay = (float) (SystemConfig::where('config_key', 'rider_return_pay')->value('config_value') ?? 10);

                RiderPayout::create([
                    'rider_id' => $order->rider_id,
                    'order_id' => $order->id,
                    'base_pay' => $returnPay,
                    'distance_pay' => 0,
                    'surge_pay' => 0,
                    'wait_pay' => 0,
                    'tip_amount' => 0,
                    'incentive_bonus' => 0,
                    'penalty' => 0,
                    'cash_collected' => 0,
                    'total_payout' => $returnPay,
                    'surge_multiplier' => 1,
                    'is_settled' => false,
                ]);
            }

            event(new OrderStatusChanged($order, 'out_for_delivery', 'abandoned'));
            $handled++;
        }

        if ($handled) {
            $this->info("Unreachable sweep handled {$handled} order(s).");
        }

        return self::SUCCESS;
    }
}
