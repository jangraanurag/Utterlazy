<?php

namespace App\Console\Commands;

use App\Services\OutboxPublisher;
use Illuminate\Console\Command;

class PublishOutbox extends Command
{
    protected $signature = 'outbox:publish
                            {--daemon : Run in an infinite loop}
                            {--batch=100 : Rows per iteration}
                            {--sleep=1 : Seconds between iterations (daemon mode)}';

    protected $description = 'Publish outbox rows to the event bus (Kafka or Redis stream)';

    public function handle(OutboxPublisher $publisher): int
    {
        $batch = (int) $this->option('batch');
        $sleep = (int) $this->option('sleep');
        $daemon = (bool) $this->option('daemon');

        do {
            $result = $publisher->publishPending($batch);

            if ($result['published'] > 0 || $result['failed'] > 0) {
                $this->info(sprintf(
                    'Outbox: published=%d failed=%d pending=%d',
                    $result['published'],
                    $result['failed'],
                    $result['pending'],
                ));
            }

            if (! $daemon) {
                break;
            }

            sleep($sleep);
        } while ($daemon);

        return self::SUCCESS;
    }
}
