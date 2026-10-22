<?php

namespace App\Jobs;

use App\Services\RiderAssignmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPendingAssignments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public $tries = 1;

    public $timeout = 60;

    public function handle(): void
    {
        try {
            $assigned = RiderAssignmentService::processPendingQueue();
            if ($assigned > 0) {
                Log::info("ProcessPendingAssignments: Assigned {$assigned} order(s) from pending queue");
            }
        } catch (\Throwable $e) {
            Log::error("ProcessPendingAssignments error: {$e->getMessage()}");
        }
    }
}
