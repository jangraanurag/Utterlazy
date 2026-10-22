<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneIdempotencyKeys extends Command
{
    protected $signature = 'idempotency:prune {--hours=24}';

    protected $description = 'Delete idempotency keys older than the given hours (default 24)';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');

        $deleted = DB::table('idempotency_keys')
            ->where('created_at', '<', now()->subHours($hours))
            ->delete();

        $this->info("Pruned {$deleted} idempotency key(s).");

        return self::SUCCESS;
    }
}
