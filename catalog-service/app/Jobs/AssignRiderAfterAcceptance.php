<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\RiderAssignmentService;
use App\Services\RouteOptimizerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AssignRiderAfterAcceptance implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public $tries = 3;

    public $backoff = 10;

    public $timeout = 30;

    public function __construct(
        public string $orderId
    ) {}

    public function handle(): void
    {
        $order = Order::find($this->orderId);

        if (! $order) {
            Log::warning("AssignRiderAfterAcceptance: Order {$this->orderId} not found");

            return;
        }

        if (! in_array($order->status, ['preparing', 'ready_for_pickup'])) {
            Log::info("AssignRiderAfterAcceptance: Order {$this->orderId} status is {$order->status}, skipping");

            return;
        }

        if ($order->rider_id) {
            Log::info("AssignRiderAfterAcceptance: Order {$this->orderId} already has rider");

            return;
        }

        // Try multi-order routing first (same customer / same restaurant)
        try {
            RouteOptimizerService::batchIfPossible($order);
        } catch (\Throwable $e) {
            Log::error("RouteOptimizerService error: {$e->getMessage()}");
        }

        // If still no rider, try single assignment
        if (! $order->fresh()->rider_id) {
            $assignment = RiderAssignmentService::assign($order);

            if ($assignment) {
                Log::info("AssignRiderAfterAcceptance: Rider {$assignment->rider_id} assigned to order {$this->orderId}");
            } else {
                Log::info("AssignRiderAfterAcceptance: No rider available for order {$this->orderId} — added to pending queue");
            }
        }
    }
}
