<?php

namespace App\Console\Commands;

use App\Services\AdAuctionService;
use Illuminate\Console\Command;

class ManageAds extends Command
{
    protected $signature = 'ads:manage';
    protected $description = 'Activate scheduled ads and pause exhausted/expired ads';

    public function handle(AdAuctionService $service): int
    {
        $activated = $service->activateScheduledAds();
        $paused = $service->pauseExhaustedAds();

        $this->info("Ads managed: {$activated} activated, {$paused} paused.");

        return self::SUCCESS;
    }
}
