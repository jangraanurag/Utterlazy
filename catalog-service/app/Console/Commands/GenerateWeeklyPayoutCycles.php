<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use App\Services\PayoutCalculationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateWeeklyPayoutCycles extends Command
{
    protected $signature = 'payouts:generate-weekly-cycles {--restaurant= : Generate for specific restaurant ID only}';

    protected $description = 'Generate payout cycles every Wednesday 8 AM for the previous Mon-Sun window';

    public function handle(PayoutCalculationService $service): int
    {
        $now = Carbon::now();
        $cycleStart = $now->copy()->subWeek()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $cycleEnd = $now->copy()->subWeek()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        $this->info("Generating payout cycles for {$cycleStart->toDateString()} to {$cycleEnd->toDateString()}");

        $query = Restaurant::where('is_active', true);

        if ($restaurantId = $this->option('restaurant')) {
            $query->where('id', $restaurantId);
        }

        $restaurants = $query->get();
        $bar = $this->output->createProgressBar($restaurants->count());
        $generated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($restaurants as $restaurant) {
            try {
                $existing = \App\Models\PayoutCycle::where('restaurant_id', $restaurant->id)
                    ->where('cycle_start', $cycleStart->toDateString())
                    ->where('cycle_end', $cycleEnd->toDateString())
                    ->exists();

                if ($existing) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                $service->generateWeeklyCycle($restaurant, $cycleStart, $cycleEnd);
                $generated++;
            } catch (\Throwable $e) {
                $this->error("Failed for {$restaurant->name}: {$e->getMessage()}");
                $errors++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done: {$generated} generated, {$skipped} skipped, {$errors} errors");

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
