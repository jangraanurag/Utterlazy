<?php

namespace App\Console\Commands;

use App\Services\IncidentService;
use Illuminate\Console\Command;

class SweepIncidents extends Command
{
    protected $signature = 'incidents:sweep';

    protected $description = 'Flag open incidents that breached their SLA (once per incident)';

    public function handle(IncidentService $incidents): int
    {
        $atRisk = $incidents->escalateAtRisk();
        $breached = $incidents->markBreaches();

        if ($atRisk > 0) {
            $this->warn("Escalated {$atRisk} incident(s) at SLA risk.");
        }

        if ($breached > 0) {
            $this->warn("Flagged {$breached} incident SLA breach(es).");
        }

        return self::SUCCESS;
    }
}
