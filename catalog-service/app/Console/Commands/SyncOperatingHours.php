<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncOperatingHours extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'restaurants:sync-operating-hours';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically toggle restaurants online/offline based on operating hours';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $restaurants = Restaurant::where('is_active', true)->get();
        $now = Carbon::now();
        $dayOfWeek = strtolower($now->englishDayOfWeek); // e.g., 'monday'
        $currentTime = $now->format('H:i'); // e.g., '14:30'

        $onlineCount = 0;
        $offlineCount = 0;

        foreach ($restaurants as $restaurant) {
            // Check if restaurant is paused (by DB column or cache)
            $isPaused = false;
            if ($restaurant->paused_until) {
                if ($restaurant->paused_until->gt(Carbon::now())) {
                    $isPaused = true;
                } else {
                    // Pause expired, clear it
                    $restaurant->update(['paused_until' => null]);
                    Cache::forget('restaurant_pause_'.$restaurant->id);
                }
            } elseif (Cache::has('restaurant_pause_'.$restaurant->id)) {
                $isPaused = true;
            }

            if ($isPaused) {
                if ($restaurant->is_accepting_orders) {
                    $restaurant->update(['is_accepting_orders' => false]);
                    $offlineCount++;
                }

                continue;
            }

            // Respect manual offline toggle during operating hours
            if ($restaurant->manually_offline) {
                if ($restaurant->is_accepting_orders) {
                    $restaurant->update(['is_accepting_orders' => false]);
                    $offlineCount++;
                }

                continue;
            }

            $operatingHours = $restaurant->operating_hours;
            $shouldBeOnline = false;

            if (is_array($operatingHours) && isset($operatingHours[$dayOfWeek])) {
                $todaySchedule = $operatingHours[$dayOfWeek];

                if (isset($todaySchedule['is_closed']) && $todaySchedule['is_closed'] == true) {
                    $shouldBeOnline = false;
                } elseif (isset($todaySchedule['open']) && isset($todaySchedule['close'])) {
                    $openTime = $todaySchedule['open'];
                    $closeTime = $todaySchedule['close'];

                    if ($openTime < $closeTime) {
                        // Standard case (e.g. 09:00 to 22:00)
                        $shouldBeOnline = ($currentTime >= $openTime && $currentTime <= $closeTime);
                    } else {
                        // Overnight case (e.g. 18:00 to 02:00)
                        $shouldBeOnline = ($currentTime >= $openTime || $currentTime <= $closeTime);
                    }
                }
            } else {
                // If no operating hours are defined, default to false (or true depending on biz logic)
                // Let's assume if it's not defined, we leave it whatever it currently is.
                continue;
            }

            if ($restaurant->is_accepting_orders != $shouldBeOnline) {
                $restaurant->update(['is_accepting_orders' => $shouldBeOnline]);
                if ($shouldBeOnline) {
                    $onlineCount++;
                } else {
                    $offlineCount++;
                }
            }
        }

        $this->info("Synced Operating Hours. Turned $onlineCount online, $offlineCount offline.");

        return Command::SUCCESS;
    }
}
