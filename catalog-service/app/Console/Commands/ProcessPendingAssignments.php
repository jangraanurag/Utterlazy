<?php

namespace App\Console\Commands;

use App\Services\RiderAssignmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessPendingAssignments extends Command
{
    protected $signature = 'riders:process-pending';

    protected $description = 'Process pending rider assignment queue (run every 2-5 minutes)';

    public function handle(): int
    {
        $this->info('Processing pending rider assignments...');

        try {
            // 1. Expire stale pending assignments (older than 120 seconds)
            $expired = RiderAssignmentService::expireStaleAssignments();
            $this->info("Expired {$expired} stale assignments");

            // 2. Try to assign riders from pending queue
            $assigned = RiderAssignmentService::processPendingQueue();
            $this->info("Assigned {$assigned} orders from pending queue");
        } catch (\Throwable $e) {
            $this->error('Error: '.$e->getMessage());
            Log::error('ProcessPendingAssignments failed: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
