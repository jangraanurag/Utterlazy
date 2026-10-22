<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PiiRetentionService;
use Illuminate\Console\Command;

class PiiRetention extends Command
{
    protected $signature = 'pii:retention
                            {--days=180 : Retention window in days (minimum 30)}
                            {--apply : Actually delete (default is dry-run)}
                            {--confirm= : Must be exactly DELETE when --apply is used}
                            {--actor= : Optional admin email recorded on the run}';

    protected $description = 'Dry-run or apply retention to PII access logs (audited, minimum 30 days)';

    public function handle(PiiRetentionService $retention): int
    {
        $days = (int) $this->option('days');
        $apply = (bool) $this->option('apply');

        try {
            $cutoff = $retention->cutoff($days);
        } catch (\DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($apply && $this->option('confirm') !== 'DELETE') {
            $this->error('Refusing to delete without --confirm=DELETE (case-sensitive).');
            $this->line('Run without --apply first to review the breakdown.');

            return self::FAILURE;
        }

        $actor = null;
        if ($email = $this->option('actor')) {
            $actor = User::where('email', $email)->first();
            if (! $actor) {
                $this->error("Actor not found: {$email}");

                return self::FAILURE;
            }
        }

        $result = $retention->purge($cutoff, $actor, ! $apply);

        $this->info(sprintf(
            '%s cutoff=%s total=%d deleted=%d',
            $result['dry_run'] ? 'DRY-RUN' : 'APPLIED',
            $result['cutoff'],
            $result['breakdown']['total'],
            $result['rows_deleted'],
        ));

        foreach ($result['breakdown']['by_action'] as $action => $count) {
            $this->line("  action {$action}: {$count}");
        }

        if ($result['dry_run']) {
            $this->warn('Nothing deleted. Re-run with --apply --confirm=DELETE after review.');
        } else {
            $this->info('Purge recorded in pii_retention_runs.');
        }

        return self::SUCCESS;
    }
}
