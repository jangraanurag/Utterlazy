<?php

namespace App\Console\Commands;

use App\Services\OutboxPublisher;
use Illuminate\Console\Command;

class PurgeProcessedOutbox extends Command
{
    protected $signature = 'outbox:purge-processed {--days=7}';

    protected $description = 'Delete published outbox rows older than the given days (default 7)';

    public function handle(OutboxPublisher $publisher): int
    {
        $days = (int) $this->option('days');

        $deleted = $publisher->purgeProcessed($days);

        $this->info("Purged {$deleted} published outbox row(s).");

        return self::SUCCESS;
    }
}
