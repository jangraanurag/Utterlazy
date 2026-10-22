<?php

namespace App\Console\Commands;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AbandonedOrderCheck extends Command
{
    protected $signature = 'orders:check-abandoned';

    protected $description = 'Mark orders as abandoned if stuck in pending/confirmed for too long';

    public function handle(): int
    {
        $abandonedMinutes = 30;

        $abandoned = Order::whereIn('status', ['pending', 'confirmed'])
            ->where('created_at', '<', Carbon::now()->subMinutes($abandonedMinutes))
            ->update(['status' => 'abandoned']);

        if ($abandoned > 0) {
            $this->info("Marked {$abandoned} order(s) as abandoned.");
        } else {
            $this->info('No abandoned orders found.');
        }

        return Command::SUCCESS;
    }
}
