<?php

namespace App\Console\Commands;

use App\Models\PricingParityCheck;
use Illuminate\Console\Command;

class PurgePricingParityChecks extends Command
{
    protected $signature = 'pricing:purge-checks {--days=7}';

    protected $description = 'Delete pricing parity checks older than the given days';

    public function handle(): int
    {
        $deleted = PricingParityCheck::where('created_at', '<', now()->subDays((int) $this->option('days')))->delete();

        $this->info("Purged {$deleted} pricing parity check(s).");

        return self::SUCCESS;
    }
}
