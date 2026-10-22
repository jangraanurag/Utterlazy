<?php

namespace App\Console\Commands;

use App\Services\Analytics\AnalyticsProjector;
use Illuminate\Console\Command;

class ProjectAnalytics extends Command
{
    protected $signature = 'analytics:project
                            {--limit=500 : Events per pass}
                            {--daemon : Keep running}
                            {--sleep=2 : Seconds between passes in daemon mode}';

    protected $description = 'Project outbox events into ClickHouse facts (no-op unless ANALYTICS_ENABLED=true)';

    public function handle(AnalyticsProjector $projector): int
    {
        if (! $projector->enabled()) {
            $this->line('Analytics disabled (ANALYTICS_ENABLED=false).');

            return self::SUCCESS;
        }

        do {
            $result = $projector->project((int) $this->option('limit'));

            if ($result['projected'] > 0 || $result['failed'] > 0) {
                $this->info(sprintf(
                    'Analytics: projected=%d failed=%d',
                    $result['projected'],
                    $result['failed'],
                ));
            }

            if (! $this->option('daemon')) {
                break;
            }

            sleep((int) $this->option('sleep'));
        } while (true);

        return self::SUCCESS;
    }
}
