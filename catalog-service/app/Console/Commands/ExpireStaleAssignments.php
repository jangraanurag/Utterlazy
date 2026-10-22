<?php

namespace App\Console\Commands;

use App\Services\RiderAssignmentService;
use Illuminate\Console\Command;

class ExpireStaleAssignments extends Command
{
    protected $signature = 'assignments:expire-stale';

    protected $description = 'Expire rider assignments not responded to within 2 minutes, then reassign';

    public function handle(): int
    {
        $expired = RiderAssignmentService::expireStaleAssignments();

        if ($expired > 0) {
            $this->info("Expired {$expired} stale rider assignment(s).");
        } else {
            $this->info('No stale assignments found.');
        }

        return Command::SUCCESS;
    }
}
