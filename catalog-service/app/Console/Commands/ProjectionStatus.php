<?php

namespace App\Console\Commands;

use App\Services\Projection\OrderProjectionService;
use Illuminate\Console\Command;

class ProjectionStatus extends Command
{
    protected $signature = 'projection:status';

    protected $description = 'Show the Java→Laravel order projection read model status';

    public function handle(OrderProjectionService $projection): int
    {
        $status = $projection->status();

        $this->info("Projected orders: {$status['total']} total · {$status['last_24h']} in 24h");
        $this->line('Latest: '.($status['latest'] ?? 'never'));

        foreach ($status['by_status'] as $state => $count) {
            $this->line("  {$state}: {$count}");
        }

        return self::SUCCESS;
    }
}
