<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use App\Services\InvoiceGenerationService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class GenerateWeeklyInvoices extends Command
{
    protected $signature = 'invoices:generate-weekly {--restaurant=} {--month}';
    protected $description = 'Generate weekly commission, ads, and recovery invoices';

    public function handle(InvoiceGenerationService $service): int
    {
        if ($this->option('restaurant')) {
            $restaurant = Restaurant::findOrFail($this->option('restaurant'));
            if ($this->option('month')) {
                $start = now()->startOfMonth();
                $end   = now()->endOfMonth();
            } else {
                $start = now()->subWeek()->startOfWeek(Carbon::MONDAY);
                $end   = now()->subWeek()->endOfWeek(Carbon::SUNDAY);
            }

            $service->generateCommissionInvoice($restaurant, $start, $end);
            $service->generateAdsInvoice($restaurant, $start, $end);
            $service->generateRecoveryInvoice($restaurant, $start, $end);
            $this->info("Invoices generated for {$restaurant->name}");
        } else {
            $results = $service->generateAllForWeek();
            $this->info("Weekly invoices generated: Commission={$results['commission']}, Ads={$results['ads']}, Recovery={$results['recovery']}");
        }

        return self::SUCCESS;
    }
}
