<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\SystemConfig;
use App\Models\VendorPayment;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateWeeklyPayouts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payouts:generate-weekly-legacy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate weekly payouts for vendors (Wednesdays for previous Mon-Sun)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Payout runs Wednesday 6:00 AM, but for the previous Mon-Sun
        // E.g., if today is Wednesday, subWeek()->startOfWeek() goes to last week Monday
        $startOfWeek = Carbon::now()->subWeek()->startOfWeek();
        $endOfWeek = Carbon::now()->subWeek()->endOfWeek();

        $this->info("Generating payouts for period: {$startOfWeek->toDateString()} to {$endOfWeek->toDateString()}");

        $restaurants = Restaurant::where('is_active', true)->get();
        $gstConfig = SystemConfig::where('config_key', 'commission_gst_rate')->first();
        $gstRate = $gstConfig ? (float) $gstConfig->config_value : 18.00;

        foreach ($restaurants as $restaurant) {
            $orders = Order::where('restaurant_id', $restaurant->id)
                ->where('status', 'delivered')
                ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
                ->get();

            if ($orders->isNotEmpty()) {
                $grossRevenue = $orders->sum('subtotal');
                $deliveryFeeShare = $orders->sum('delivery_fee_restaurant');
                $totalOrderValue = $grossRevenue;

                $commissionAmount = round($grossRevenue * ($restaurant->commission_rate / 100), 2);
                $gstAmount = round($commissionAmount * ($gstRate / 100), 2);

                // Assuming no refunds or sponsorships in this basic iteration
                $totalSponsorship = 0;
                $sponsorshipGst = 0;
                $totalRefunds = 0;
                $totalDiscounts = 0;

                $netPayable = round($totalOrderValue - $commissionAmount - $gstAmount - $totalSponsorship - $sponsorshipGst - $totalRefunds - $deliveryFeeShare, 2);

                VendorPayment::create([
                    'restaurant_id' => $restaurant->id,
                    'payment_period' => "{$startOfWeek->format('Y-m-d')} to {$endOfWeek->format('Y-m-d')}",
                    'week_start' => $startOfWeek->toDateString(),
                    'week_end' => $endOfWeek->toDateString(),
                    'total_order_value' => $totalOrderValue,
                    'total_discounts' => $totalDiscounts,
                    'total_commission' => $commissionAmount,
                    'commission_gst' => $gstAmount,
                    'total_sponsorship' => $totalSponsorship,
                    'sponsorship_gst' => $sponsorshipGst,
                    'total_refunds' => $totalRefunds,
                    'net_payable' => $netPayable,
                    'status' => 'pending',
                ]);
            }
        }

        $this->info('Weekly payouts generated successfully as pending.');
    }
}
