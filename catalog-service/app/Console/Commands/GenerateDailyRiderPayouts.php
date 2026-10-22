<?php

namespace App\Console\Commands;

use App\Models\Rider;
use App\Models\RiderPayout;
use App\Models\RiderPayoutCycle;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateDailyRiderPayouts extends Command
{
    protected $signature = 'rider:generate-payouts {--date= : Date to generate payouts for (Y-m-d)}';

    protected $description = 'Generate daily payout cycles for all riders (runs at 8 AM)';

    public function handle(): int
    {
        $date = $this->option('date') ?? Carbon::yesterday()->toDateString();
        $this->info("Generating payout cycles for {$date}...");

        $riders = Rider::whereHas('payouts', function ($q) use ($date) {
            $q->whereDate('created_at', $date);
        })->get();

        if ($riders->isEmpty()) {
            $this->warn('No riders with payouts found for this date.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($riders->count());
        $bar->start();

        $created = 0;
        foreach ($riders as $rider) {
            $existing = RiderPayoutCycle::where('rider_id', $rider->id)
                ->where('cycle_date', $date)
                ->exists();

            if ($existing) {
                $bar->advance();
                continue;
            }

            $payouts = RiderPayout::where('rider_id', $rider->id)
                ->whereDate('created_at', $date)
                ->get();

            if ($payouts->isEmpty()) {
                // Create zero-amount cycle for continuity
                RiderPayoutCycle::create([
                    'rider_id' => $rider->id,
                    'cycle_date' => $date,
                    'status' => 'generated',
                    'total_orders' => 0,
                    'net_payable' => 0,
                ]);
                $created++;
                $bar->advance();
                continue;
            }

            $totalOrders = $payouts->count();
            $totalDistance = $payouts->sum(function ($p) {
                return $p->order?->delivery_distance_km ?? 0;
            });

            RiderPayoutCycle::create([
                'rider_id' => $rider->id,
                'cycle_date' => $date,
                'status' => 'generated',
                'total_orders' => $totalOrders,
                'total_distance_km' => $totalDistance,
                'distance_pay' => $payouts->sum('distance_pay'),
                'base_pay' => $payouts->sum('base_pay'),
                'surge_pay' => $payouts->sum('surge_pay'),
                'wait_pay' => $payouts->sum('wait_pay'),
                'tip_amount' => $payouts->sum('tip_amount'),
                'incentive_bonus' => $payouts->sum('incentive_bonus'),
                'penalty' => $payouts->sum('penalty'),
                'net_payable' => $payouts->sum('total_payout'),
            ]);

            $created++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Created {$created} payout cycles for {$date}.");

        return self::SUCCESS;
    }
}
