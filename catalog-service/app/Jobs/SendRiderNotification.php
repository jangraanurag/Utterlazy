<?php

namespace App\Jobs;

use App\Models\Rider;
use App\Services\FcmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendRiderNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        public string $riderId,
        public array $data,
        public ?string $title = null,
        public ?string $body = null,
    ) {}

    public function handle(FcmService $fcm): void
    {
        $rider = Rider::find($this->riderId);
        if (! $rider) {
            return;
        }

        // Only send if rider is still online
        if (! $rider->is_online) {
            Log::info("SendRiderNotification: Rider {$this->riderId} is offline, skipping");

            return;
        }

        // Send to all rider devices
        $sent = $fcm->sendToRider($rider, $this->data, $this->title, $this->body);

        if ($sent === 0) {
            Log::warning("SendRiderNotification: No FCM devices for rider {$this->riderId}");
            // Do NOT throw — this would retry the job and delay the pending queue.
            // The rider may not have registered a token yet (cold start).
            // PendingAssignment queue will handle fallback.
        }
    }
}
