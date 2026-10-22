<?php

namespace App\Console\Commands;

use App\Services\MetricAggregationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AggregateDailyMetrics extends Command
{
    protected $signature = 'metrics:aggregate-daily {--date=} {--restaurant=} {--from=} {--to=} {--backfill}';

    protected $description = 'Aggregate daily metrics from raw orders/events into fact tables';

    public function handle(MetricAggregationService $service): void
    {
        if ($dateOpt = $this->option('date')) {
            $date = Carbon::parse($dateOpt);

            if ($restaurantId = $this->option('restaurant')) {
                $restaurant = \App\Models\Restaurant::findOrFail($restaurantId);
                $service->aggregateForRestaurant($restaurant, $date);
                $this->info("Aggregated metrics for restaurant {$restaurant->id} on {$date->toDateString()}");
            } else {
                $service->aggregateForAllRestaurants($date);
                $this->info("Aggregated metrics for all restaurants on {$date->toDateString()}");
            }

            return;
        }

        if ($from = $this->option('from')) {
            $start = Carbon::parse($from);
            $end = $this->option('to') ? Carbon::parse($this->option('to')) : $start;
            $service->aggregateForDateRange($start, $end);
            $this->info("Aggregated metrics from {$start->toDateString()} to {$end->toDateString()}");

            return;
        }

        if ($this->option('backfill')) {
            $firstOrder = \App\Models\Order::where('status', 'delivered')
                ->orderBy('created_at')
                ->first();

            if ($firstOrder) {
                $start = $firstOrder->created_at->startOfDay();
                $end = Carbon::yesterday()->endOfDay();
                $service->aggregateForDateRange($start, $end);
                $this->info("Backfilled metrics from {$start->toDateString()} to {$end->toDateString()}");
            } else {
                $this->warn('No delivered orders found — nothing to backfill');
            }

            return;
        }

        $service->aggregateForAllRestaurants(Carbon::yesterday());
        $this->info('Aggregated metrics for yesterday');
    }
}
