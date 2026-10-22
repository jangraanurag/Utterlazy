<?php

namespace App\Console\Commands;

use App\Services\ApprovalWorkflowEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckApprovalSlas extends Command
{
    protected $signature = 'approvals:check-sla';

    protected $description = 'Auto-escalate approval workflow items past their SLA (§5.3)';

    public function handle(): int
    {
        try {
            $result = ApprovalWorkflowEngine::checkSlaTimeouts();
            Log::info('approvals:check-sla', $result);
            $this->info('SLA check done: ' . json_encode($result));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('approvals:check-sla failed', ['error' => $e->getMessage()]);
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
