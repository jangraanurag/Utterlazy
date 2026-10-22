<?php

namespace App\Listeners;

use App\Events\RiderAssigned;
use App\Services\FcmNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendRiderAssignmentFcmNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;
    public int $backoff = 5;

    public function __construct(
        private FcmNotificationService $fcmService
    ) {}

    public function handle(RiderAssigned $event): void
    {
        $tokens = $this->fcmService->getTokensForUsers('rider', [$event->riderId]);

        if (!empty($tokens)) {
            $this->fcmService->notifyRiderAssigned($tokens, $event->assignmentData);
        }
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error('SendRiderAssignmentFcmNotification failed: ' . $exception->getMessage());
    }
}
