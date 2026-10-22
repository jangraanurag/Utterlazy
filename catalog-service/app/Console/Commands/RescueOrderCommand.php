<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('utterlazy:rescue-orders')]
#[Description('Find pending abandoned carts and trigger rescue flow')]
class RescueOrderCommand extends Command
{
    public function handle()
    {
        $this->info('Scanning for abandoned orders...');

        // Find orders that are 'pending' and older than 15 minutes
        $abandonedOrders = Order::where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(15))
            ->get();

        $count = 0;
        foreach ($abandonedOrders as $order) {
            // Mark as abandoned, flag for rescue
            $rescueCode = 'RESCUE'.rand(1000, 9999);

            $order->update([
                'status' => 'abandoned',
                'rescue_discount_code' => $rescueCode,
            ]);

            // In a real app, we would dispatch a job to send an email/push notification here
            // \App\Jobs\SendRescueNotification::dispatch($order->user_id, $rescueCode);

            $count++;
        }

        $this->info("Rescued {$count} abandoned orders.");
    }
}
