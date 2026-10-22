<?php

namespace App\Console\Commands;

use App\Events\NewAssignmentForRider;
use App\Events\NewOrderForVendor;
use App\Events\OrderStatusChanged;
use App\Models\CallQueue;
use App\Models\Order;
use App\Models\RiderAssignment;
use App\Services\RiderAssignmentService;
use App\Services\RouteOptimizerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecoverWorkflowState extends Command
{
    protected $signature = 'workflow:recover {--dry-run : Show what would be done without actually doing it}';

    protected $description = 'Recover in-flight orders, assignments, and trips after server restart';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $recovered = [
            'vendor_notified' => 0,
            'rider_notified' => 0,
            'status_broadcast' => 0,
            'jobs_released' => 0,
            'calls_requeued' => 0,
            'active_calls_reset' => 0,
            'riders_reassigned' => 0,
            'pending_assignments' => 0,
        ];

        $recovered['vendor_notified'] = $this->recoverVendorNotifications($dryRun);
        $recovered['rider_notified'] = $this->recoverRiderAssignments($dryRun);
        $recovered['status_broadcast'] = $this->rebroadcastActiveOrders($dryRun);
        $recovered['jobs_released'] = $this->releaseStaleJobs($dryRun);
        $recovered['calls_requeued'] = $this->recoverCallQueue($dryRun);
        $recovered['active_calls_reset'] = $this->resetActiveCallsCounter($dryRun);
        $recovered['riders_reassigned'] = $this->reassignRidersForActiveOrders($dryRun);

        $this->table(
            ['Recovery Step', 'Count'],
            [
                ['Vendors re-notified', $recovered['vendor_notified']],
                ['Riders re-notified', $recovered['rider_notified']],
                ['Order status re-broadcast', $recovered['status_broadcast']],
                ['Stale jobs released', $recovered['jobs_released']],
                ['Stuck calls re-queued', $recovered['calls_requeued']],
                ['Active calls counter reset', $recovered['active_calls_reset']],
                ['Riders re-assigned', $recovered['riders_reassigned']],
            ]
        );

        Log::info('Workflow recovery completed', $recovered);

        return Command::SUCCESS;
    }

    private function recoverVendorNotifications(bool $dryRun): int
    {
        $orders = Order::where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(30))
            ->where('created_at', '<=', now()->subMinutes(2))
            ->get();

        foreach ($orders as $order) {
            $restaurantId = $order->restaurant_id;

            if ($dryRun) {
                $this->line("  [DRY-RUN] Would re-notify vendor {$restaurantId} about order #{$order->id}");

                continue;
            }

            try {
                event(new NewOrderForVendor($order, $restaurantId));
                $this->line("  Re-notified vendor {$restaurantId} about order #{$order->id}");
            } catch (\Throwable $e) {
                Log::error("Failed to re-notify vendor for order #{$order->id}: {$e->getMessage()}");
            }
        }

        return $orders->count();
    }

    private function recoverRiderAssignments(bool $dryRun): int
    {
        $assignments = RiderAssignment::where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(2))
            ->get();

        foreach ($assignments as $assignment) {
            if ($assignment->order && $assignment->order->status === 'preparing') {
                if ($dryRun) {
                    $this->line("  [DRY-RUN] Would re-notify rider #{$assignment->rider_id} about assignment #{$assignment->id}");

                    continue;
                }

                try {
                    event(new NewAssignmentForRider($assignment));
                    $this->line("  Re-notified rider #{$assignment->rider_id} about assignment #{$assignment->id}");
                } catch (\Throwable $e) {
                    Log::error("Failed to re-notify rider for assignment #{$assignment->id}: {$e->getMessage()}");
                }
            }
        }

        return $assignments->count();
    }

    private function rebroadcastActiveOrders(bool $dryRun): int
    {
        $activeStatuses = ['preparing', 'ready_for_pickup', 'out_for_delivery'];
        $orders = Order::whereIn('status', $activeStatuses)
            ->where('created_at', '>=', now()->subHours(4))
            ->get();

        foreach ($orders as $order) {
            if ($dryRun) {
                $this->line("  [DRY-RUN] Would re-broadcast '{$order->status}' for order #{$order->id}");

                continue;
            }

            try {
                event(new OrderStatusChanged($order, $order->status, $order->status));
                $this->line("  Re-broadcast '{$order->status}' for order #{$order->id}");
            } catch (\Throwable $e) {
                Log::error("Failed to re-broadcast status for order #{$order->id}: {$e->getMessage()}");
            }
        }

        return $orders->count();
    }

    private function releaseStaleJobs(bool $dryRun): int
    {
        $cutoff = now()->subMinutes(5)->timestamp;

        $query = DB::table('jobs')
            ->whereNotNull('reserved_at')
            ->where('reserved_at', '<', $cutoff);

        if ($dryRun) {
            return $query->count();
        }

        $released = $query->update([
            'reserved_at' => null,
            'attempts' => DB::raw('attempts + 1'),
        ]);

        if ($released > 0) {
            $this->line("  Released {$released} stale reserved job(s)");
        }

        return $released;
    }

    private function recoverCallQueue(bool $dryRun): int
    {
        $stuckCalls = CallQueue::where('status', 'processing')->get();

        foreach ($stuckCalls as $call) {
            if ($dryRun) {
                $this->line("  [DRY-RUN] Would re-queue stuck call #{$call->id} ({$call->call_type})");

                continue;
            }

            $call->update(['status' => 'pending', 'scheduled_at' => now()]);
        }

        if ($stuckCalls->isNotEmpty()) {
            $this->line("  Re-queued {$stuckCalls->count()} stuck call(s)");
        }

        return $stuckCalls->count();
    }

    private function resetActiveCallsCounter(bool $dryRun): int
    {
        $current = (int) Cache::get('enablex:active_calls', 0);

        if ($current <= 0) {
            return 0;
        }

        if (! $dryRun) {
            Cache::put('enablex:active_calls', 0);
            $this->line("  Reset enablex:active_calls from {$current} to 0");
        } else {
            $this->line("  [DRY-RUN] Would reset enablex:active_calls from {$current} to 0");
        }

        return $current;
    }

    private function reassignRidersForActiveOrders(bool $dryRun): int
    {
        $orders = Order::whereIn('status', ['preparing', 'ready_for_pickup'])
            ->whereNull('rider_id')
            ->where('created_at', '>=', now()->subHours(2))
            ->get();

        $count = 0;
        foreach ($orders as $order) {
            if ($dryRun) {
                $this->line("  [DRY-RUN] Would re-assign rider for order #{$order->id} ({$order->status})");

                continue;
            }

            try {
                // Try multi-order routing first
                try {
                    RouteOptimizerService::batchIfPossible($order);
                } catch (\Throwable $e) {
                    Log::error("Recovery: RouteOptimizerService error for order #{$order->id}: {$e->getMessage()}");
                }

                // If still no rider, single assignment
                if (! $order->fresh()->rider_id) {
                    RiderAssignmentService::assign($order);
                    $this->line("  Re-assigned rider for order #{$order->id}");
                    $count++;
                }
            } catch (\Throwable $e) {
                Log::error("Recovery: Failed to reassign rider for order #{$order->id}: {$e->getMessage()}");
            }
        }

        return $dryRun ? $orders->count() : $count;
    }
}
