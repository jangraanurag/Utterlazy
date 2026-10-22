<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Services\FcmNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendOrderStatusFcmNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;
    public int $backoff = 5;

    public function __construct(
        private FcmNotificationService $fcmService
    ) {}

    public function handle(OrderStatusChanged $event): void
    {
        $tokens = $this->fcmService->getTokensForUsers($event->recipientType, $event->recipientIds);

        if (!empty($tokens)) {
            $this->fcmService->notifyOrderStatusChanged($tokens, $event->statusData);
        }
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error('SendOrderStatusFcmNotification failed: ' . $exception->getMessage());
    }
}
