<?php

namespace App\Listeners;

use App\Events\NewOrderPlaced;
use App\Services\FcmNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendNewOrderFcmNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;
    public int $backoff = 5;

    public function __construct(
        private FcmNotificationService $fcmService
    ) {}

    public function handle(NewOrderPlaced $event): void
    {
        $vendorId = $event->vendorId;
        $tokens = $this->fcmService->getTokensForUsers('restaurant', [$vendorId]);

        if (!empty($tokens)) {
            $this->fcmService->notifyNewOrder($tokens, $event->orderData);
        }
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error('SendNewOrderFcmNotification failed: ' . $exception->getMessage());
    }
}
