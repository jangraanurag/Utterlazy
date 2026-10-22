<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('restaurants:daily-reset')]
#[Description('Reset manual offline flags and clear pauses for all restaurants at 6:00 AM daily')]
class DailyResetRestaurants extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = Restaurant::where('is_active', true)
            ->where('permanent_offline', false)
            ->update([
                'manually_offline' => false,
                'paused_until' => null,
            ]);

        $restaurants = Restaurant::where('is_active', true)
            ->where('permanent_offline', false)
            ->get();
        foreach ($restaurants as $r) {
            Cache::forget('restaurant_pause_'.$r->id);
        }

        $this->info("Successfully reset online/offline state for {$count} active restaurants at 6:00 AM.");

        return Command::SUCCESS;
    }
}
