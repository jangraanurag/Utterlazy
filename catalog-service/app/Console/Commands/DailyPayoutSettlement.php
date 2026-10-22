<?php

namespace App\Console\Commands;

use App\Models\RiderPayout;
use Illuminate\Console\Command;

class DailyPayoutSettlement extends Command
{
    protected $signature = 'payouts:settle-daily';

    protected $description = 'Auto-settle pending rider payouts from yesterday';

    public function handle(): int
    {
        $unsettled = RiderPayout::where('is_settled', false)
            ->whereHas('order', function ($q) {
                $q->where('delivered_at', '<', now()->subDay()->endOfDay());
            })
            ->get();

        $settled = 0;
        foreach ($unsettled as $payout) {
            $payout->update(['is_settled' => true, 'settled_at' => now()]);
            $settled++;
        }

        if ($settled > 0) {
            $this->info("Settled {$settled} rider payout(s).");
        } else {
            $this->info('No pending payouts to settle.');
        }

        return Command::SUCCESS;
    }
}
