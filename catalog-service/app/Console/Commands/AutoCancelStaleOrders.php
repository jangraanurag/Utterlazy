<?php

namespace App\Console\Commands;

use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Services\OrderReversalService;
use Illuminate\Console\Command;

class AutoCancelStaleOrders extends Command
{
    protected $signature = 'orders:cancel-stale';

    protected $description = 'Auto-cancel pending orders that vendors have not accepted within 3 minutes, and reverse prepaid money';

    public function handle(): int
    {
        $timeoutMinutes = 3;

        $staleOrders = Order::where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes($timeoutMinutes))
            ->get();

        $cancelled = 0;
        foreach ($staleOrders as $order) {
            $order->update([
                'status' => 'cancelled',
                'cancel_reason' => 'restaurant_timeout',
            ]);

            OrderReversalService::reverse($order, 'restaurant_timeout');

            event(new OrderStatusChanged($order, 'pending', 'cancelled'));

            $cancelled++;
            $this->info("Auto-cancelled Order #{$order->id} due to restaurant inactivity");
        }

        $this->info("Auto-cancelled {$cancelled} stale order(s).");

        return Command::SUCCESS;
    }
}
