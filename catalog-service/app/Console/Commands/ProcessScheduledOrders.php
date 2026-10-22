<?php

namespace App\Console\Commands;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Models\ScheduledOrder;
use Illuminate\Console\Command;

class ProcessScheduledOrders extends Command
{
    protected $signature = 'scheduled-orders:process';

    protected $description = 'Process scheduled orders that are due';

    public function handle(): int
    {
        $dueOrders = ScheduledOrder::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();

        $processed = 0;
        foreach ($dueOrders as $scheduledOrder) {
            try {
                $restaurant = Restaurant::find($scheduledOrder->restaurant_id);
                if (! $restaurant || ! $restaurant->is_active || ! $restaurant->is_accepting_orders) {
                    $scheduledOrder->update([
                        'status' => 'cancelled',
                        'notes' => trim(((string) $scheduledOrder->notes).' | auto-cancelled: restaurant closed at slot'),
                    ]);

                    continue;
                }

                $order = Order::create([
                    'user_id' => $scheduledOrder->user_id,
                    'restaurant_id' => $scheduledOrder->restaurant_id,
                    'status' => 'pending',
                    'payment_mode' => $scheduledOrder->payment_mode,
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'grand_total' => 0,
                ]);

                foreach ($scheduledOrder->items as $item) {
                    $menuItem = MenuItem::find($item['menu_item_id']);
                    if ($menuItem) {
                        OrderItem::create([
                            'order_id' => $order->id,
                            'menu_item_id' => $menuItem->id,
                            'quantity' => $item['quantity'],
                            'unit_price' => $menuItem->price,
                            'total_price' => $menuItem->price * $item['quantity'],
                        ]);
                    }
                }

                $scheduledOrder->update([
                    'status' => 'placed',
                    'order_id' => $order->id,
                ]);

                $processed++;
            } catch (\Exception $e) {
                $this->error("Failed to process scheduled order {$scheduledOrder->id}: ".$e->getMessage());
            }
        }

        if ($processed > 0) {
            $this->info("Processed {$processed} scheduled order(s).");
        } else {
            $this->info('No scheduled orders due.');
        }

        return Command::SUCCESS;
    }
}
