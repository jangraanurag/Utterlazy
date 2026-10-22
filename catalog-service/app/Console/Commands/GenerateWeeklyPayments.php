<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\VendorPaymentController;
use App\Models\Restaurant;
use App\Models\VendorPayment;
use Illuminate\Console\Command;

class GenerateWeeklyPayments extends Command
{
    protected $signature = 'payouts:generate-weekly {--week-start=} {--week-end=}';

    protected $description = 'Auto-generate weekly payouts for all active restaurants for the previous week';

    public function handle(): int
    {
        $this->info('Starting weekly payout generation...');

        $weekStart = $this->option('week-start') ?: now()->subWeek()->startOfWeek()->toDateString();
        $weekEnd = $this->option('week-end') ?: now()->subWeek()->endOfWeek()->toDateString();

        $restaurants = Restaurant::where('is_active', true)->get();
        $generated = 0;

        $controller = app(VendorPaymentController::class);

        foreach ($restaurants as $restaurant) {
            $exists = VendorPayment::where('restaurant_id', $restaurant->id)
                ->whereDate('week_start', $weekStart)
                ->whereDate('week_end', $weekEnd)
                ->exists();

            if ($exists) {
                $this->line("Payout already exists for restaurant {$restaurant->name} for period {$weekStart} to {$weekEnd}");

                continue;
            }

            try {
                // Accessing generateForRestaurant using Reflection since it is private
                $method = new \ReflectionMethod(VendorPaymentController::class, 'generateForRestaurant');
                $method->setAccessible(true);
                $method->invoke($controller, $restaurant->id, $weekStart, $weekEnd);

                $this->info("Generated payout for restaurant {$restaurant->name}");
                $generated++;
            } catch (\Exception $e) {
                $this->error("Error generating payout for restaurant {$restaurant->name}: ".$e->getMessage());
            }
        }

        $this->info("Weekly payout generation completed. Total generated: {$generated}");

        return Command::SUCCESS;
    }
}
