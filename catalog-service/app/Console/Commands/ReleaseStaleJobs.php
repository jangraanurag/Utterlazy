<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReleaseStaleJobs extends Command
{
    protected $signature = 'queue:release-stale {minutes=5 : Minutes after which a reserved job is considered stale}';

    protected $description = 'Release jobs that have been reserved for too long (worker crashed)';

    public function handle(): int
    {
        $minutes = (int) $this->argument('minutes');
        $cutoff = now()->subMinutes($minutes)->timestamp;

        $released = DB::table('jobs')
            ->whereNotNull('reserved_at')
            ->where('reserved_at', '<', $cutoff)
            ->update([
                'reserved_at' => null,
                'attempts' => DB::raw('attempts + 1'),
            ]);

        $this->info("Released {$released} stale reserved job(s).");

        // Also check failed_jobs — retry failed jobs that might have failed due to worker crash
        $recentlyFailed = DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subMinutes($minutes * 2))
            ->count();

        if ($recentlyFailed > 0) {
            $this->warn("{$recentlyFailed} recent failed job(s) found. Run 'php artisan queue:retry all' to retry them.");
        }

        return Command::SUCCESS;
    }
}
