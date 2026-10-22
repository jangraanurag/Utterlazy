<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\RiderAssignmentService;
use Illuminate\Console\Command;

class AutoAssignRiders extends Command
{
    protected $signature = 'orders:auto-assign-riders';

    protected $description = 'Auto-assign riders to orders stuck in preparing/ready_for_pickup without a rider';

    public function handle(): int
    {
        $orders = Order::whereIn('status', ['preparing', 'ready_for_pickup'])
            ->whereNull('rider_id')
            ->where('created_at', '>', now()->subHours(2))
            ->get();

        $assigned = 0;
        foreach ($orders as $order) {
            $existingAssignments = $order->riderAssignments()->count();
            if ($existingAssignments >= 3) {
                continue;
            }

            $assignment = RiderAssignmentService::assign($order);
            if ($assignment) {
                $assigned++;
                $this->info("Assigned rider to Order #{$order->id}");
            }
        }

        $this->info("Auto-assigned riders to {$assigned} order(s).");

        return Command::SUCCESS;
    }
}
