<?php

namespace App\Listeners;

use App\Events\AdminStatsUpdated;
use App\Services\AdminDashboardService;
use Illuminate\Support\Facades\Log;

class BroadcastAdminStats
{
    public function handle($event): void
    {
        try {
            $service = new AdminDashboardService;
            $stats = $service->getLiveStats();
            broadcast(new AdminStatsUpdated($stats));
        } catch (\Throwable $e) {
            Log::error('Failed to broadcast admin stats: '.$e->getMessage());
        }
    }
}
