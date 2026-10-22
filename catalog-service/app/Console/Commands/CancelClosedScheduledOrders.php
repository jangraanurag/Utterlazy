<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use App\Models\ScheduledOrder;
use Illuminate\Console\Command;

class CancelClosedScheduledOrders extends Command
{
    protected $signature = 'scheduled-orders:cancel-closed {--minutes=60 : Look-ahead window}';

    protected $description = 'Cancel scheduled orders whose restaurant is closed/inactive before the slot';

    public function handle(): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $cutoff = now()->addMinutes($minutes);

        $orders = ScheduledOrder::where('status', 'scheduled')
            ->where('scheduled_at', '<=', $cutoff)
            ->get();

        $cancelled = 0;

        foreach ($orders as $scheduled) {
            $restaurant = Restaurant::find($scheduled->restaurant_id);

            if ($restaurant && $restaurant->is_active && $restaurant->is_accepting_orders) {
                continue;
            }

            $scheduled->update([
                'status' => 'cancelled',
                'notes' => trim(((string) $scheduled->notes).' | auto-cancelled: restaurant closed'),
            ]);
            $cancelled++;
        }

        if ($cancelled > 0) {
            $this->warn("Cancelled {$cancelled} scheduled order(s) for closed restaurants.");
        }

        return self::SUCCESS;
    }
}
