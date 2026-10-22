<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderReversalService;
use Illuminate\Console\Command;

class RetryPendingRefunds extends Command
{
    protected $signature = 'refunds:retry-pending {--limit=50 : Max orders per run}';

    protected $description = 'Retry gateway refunds stuck in refund_pending (exponential attention, capped attempts)';

    public function handle(): int
    {
        $orders = Order::where('payment_status', OrderReversalService::STATUS_REFUND_PENDING)
            ->where('refund_attempts', '<', OrderReversalService::MAX_RETRY_ATTEMPTS)
            ->orderBy('refund_retried_at')
            ->orderBy('created_at')
            ->limit((int) $this->option('limit'))
            ->get();

        $succeeded = 0;
        $failed = 0;

        foreach ($orders as $order) {
            if (OrderReversalService::retryPending($order)) {
                $succeeded++;
            } else {
                $failed++;
            }
        }

        if ($orders->isNotEmpty()) {
            $this->info("Refund retries: succeeded={$succeeded} failed={$failed}");
        }

        $exhausted = Order::where('payment_status', OrderReversalService::STATUS_REFUND_PENDING)
            ->where('refund_attempts', '>=', OrderReversalService::MAX_RETRY_ATTEMPTS)
            ->count();

        if ($exhausted > 0) {
            $this->warn("{$exhausted} refund(s) exhausted retries — finance must resolve manually.");
        }

        return self::SUCCESS;
    }
}
