<?php

namespace App\Console\Commands;

use App\Models\Rider;
use App\Services\RiderDashboardService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AggregateDailyRiderMetrics extends Command
{
    protected $signature = 'rider:aggregate-metrics {--date= : Date to aggregate for (Y-m-d)}';

    protected $description = 'Aggregate daily rider metrics from completed orders';

    public function handle(): int
    {
        $date = $this->option('date') ?? Carbon::yesterday()->toDateString();
        $this->info("Aggregating rider metrics for {$date}...");

        $riders = Rider::whereHas('payouts', function ($q) use ($date) {
            $q->whereDate('created_at', $date);
        })->orWhereHas('assignments', function ($q) use ($date) {
            $q->whereDate('created_at', $date);
        })->get();

        if ($riders->isEmpty()) {
            $this->warn('No riders with activity found for this date.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($riders->count());
        $bar->start();

        foreach ($riders as $rider) {
            RiderDashboardService::aggregateDay($rider->id, $date);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Aggregated metrics for {$riders->count()} riders.");

        return self::SUCCESS;
    }
}
