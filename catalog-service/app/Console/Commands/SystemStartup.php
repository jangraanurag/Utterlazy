<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SystemStartup extends Command
{
    protected $signature = 'system:startup';

    protected $description = 'Run full crash recovery on server boot — workflow, calls, assignments';

    public function handle(): int
    {
        $this->info('=== System Startup Recovery ===');

        // Clear any stale EnableX active-call counter from the crashed server
        Cache::forget('enablex:active_calls');

        // Run the full workflow recovery (re-notify, re-assign, re-queue calls)
        $this->call('workflow:recover');

        $this->info('=== Startup Recovery Complete ===');
        Log::info('System startup recovery triggered on boot');

        return Command::SUCCESS;
    }
}
