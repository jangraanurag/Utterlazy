<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\PendingAssignment;
use App\Services\RiderAssignmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcilePendingAssignments extends Command
{
    protected $signature = 'assignments:reconcile';

    protected $description = 'Every 30s: scan eligible orders, ensure in pending queue, then process the queue';

    public function handle(): int
    {
        $reconciled = 0;
        $processed = 0;

        // Step 1: Single efficient query — find eligible orders NOT in pending queue
        $eligibleOrders = Order::leftJoin('pending_assignments', 'orders.id', '=', 'pending_assignments.order_id')
            ->whereNull('pending_assignments.id')
            ->whereNull('orders.rider_id')
            ->whereIn('orders.status', ['preparing', 'ready_for_pickup'])
            ->where('orders.created_at', '>', now()->subHours(2))
            ->select('orders.id')
            ->pluck('orders.id');

        if ($eligibleOrders->isNotEmpty()) {
            $now = now();
            $insertData = $eligibleOrders->map(fn ($id) => [
                'order_id' => $id,
                'attempt_count' => 0,
                'created_at' => $now,
            ])->toArray();

            // Batch insert — ignore duplicates
            foreach (array_chunk($insertData, 50) as $chunk) {
                PendingAssignment::insertOrIgnore($chunk);
            }

            $reconciled = count($insertData);
        }

        // Step 2: Process the pending queue (try to assign riders)
        try {
            $processed = RiderAssignmentService::processPendingQueue();
        } catch (\Throwable $e) {
            Log::error('ReconcilePendingAssignments: processPendingQueue error: '.$e->getMessage());
        }

        if ($reconciled > 0 || $processed > 0) {
            $this->line("Reconciled {$reconciled}, processed {$processed}");
        }

        return Command::SUCCESS;
    }
}
