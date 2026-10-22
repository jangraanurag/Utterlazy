<?php

namespace App\Console\Commands;

use App\Services\Projection\OrderEventSourceFactory;
use App\Services\Projection\OrderProjectionService;
use App\Services\Projection\ProjectionRunner;
use Illuminate\Console\Command;

class ConsumeOrderProjection extends Command
{
    protected $signature = 'projection:consume
                            {--transport= : redis|kafka (default from PROJECTION_TRANSPORT)}
                            {--once : Read one batch and exit}
                            {--batch=100 : Max messages per read}
                            {--sleep-ms=3000 : Block time per read}';

    protected $description = 'Project Java order events into the Laravel read model';

    public function handle(
        OrderEventSourceFactory $factory,
        ProjectionRunner $runner,
        OrderProjectionService $projection,
    ): int {
        try {
            $source = $factory->make($this->option('transport') ?: null);
        } catch (\Throwable $e) {
            $this->error('Cannot open event source: '.$e->getMessage());

            return self::FAILURE;
        }

        try {
            do {
                $result = $runner->run(
                    $source,
                    $projection,
                    (int) $this->option('batch'),
                    (int) $this->option('sleep-ms'),
                );

                if ($result['applied'] > 0 || $result['failed'] > 0) {
                    $this->info(sprintf(
                        'Projection: read=%d applied=%d failed=%d',
                        $result['read'],
                        $result['applied'],
                        $result['failed'],
                    ));
                }

                if ($result['failed'] > 0) {
                    return self::FAILURE;
                }

                if ($this->option('once')) {
                    break;
                }
            } while (true);
        } finally {
            $source->close();
        }

        return self::SUCCESS;
    }
}
