<?php

namespace App\Console\Commands;

use App\Models\RiderAssignment;
use App\Services\EnableXService;
use App\Services\FcmService;
use App\Services\RiderAssignmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CallRiderReminder extends Command
{
    protected $signature = 'orders:call-rider-reminder';

    protected $description = 'Call riders with pending assignments, retry twice, then auto-reject and re-assign';

    private const DELAY_SECONDS = 60;

    private const MAX_CALLS = 2;

    private const CALL_INTERVAL_SECONDS = 30;

    public function __construct(
        private EnableXService $enableX,
        private FcmService $fcm,
        private RiderAssignmentService $riderAssignment
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $cutoff = now()->subSeconds(self::DELAY_SECONDS);

        $assignments = RiderAssignment::where('status', 'pending')
            ->where('created_at', '<=', $cutoff)
            ->with(['rider', 'order'])
            ->get();

        $count = 0;
        foreach ($assignments as $assignment) {
            $rider = $assignment->rider;
            $order = $assignment->order;

            if (! $rider || ! $rider->phone || ! $order) {
                continue;
            }

            $cacheKey = "rider_call:{$assignment->id}";
            $callData = Cache::get($cacheKey, ['attempts' => 0, 'last_call_at' => null]);
            $attempt = $callData['attempts'];

            if ($attempt >= self::MAX_CALLS) {
                $this->escalateAssignment($assignment, $order);

                continue;
            }

            $result = $this->enableX->callDirect($rider->phone, [
                'name' => 'rider-delivery-reminder',
                'owner_ref' => $order->id,
                'tts_text' => 'Namaste! Aapke paas UtterLazy par ek naya delivery request aaya hai. Kripya app check karein.',
                'tts_language' => 'hi-IN',
                'tts_voice' => 'female',
                'custom_data' => [
                    'order_id' => $order->id,
                    'assignment_id' => $assignment->id,
                    'call_type' => 'rider_reminder',
                    'attempt' => $attempt + 1,
                ],
            ]);

            if ($result) {
                Cache::put($cacheKey, [
                    'attempts' => $attempt + 1,
                    'last_call_at' => now()->toIso8601String(),
                ], now()->addHours(1));

                $this->fcm->sendToUser($rider, 'rider', [
                    'type' => 'delivery_reminder_call',
                    'order_id' => $order->id,
                    'assignment_id' => $assignment->id,
                ], 'Delivery Reminder', 'Please check the app for a new delivery request.');
            }

            $count++;
        }

        if ($count > 0) {
            Log::info("CallRiderReminder: Called {$count} riders");
        }

        return Command::SUCCESS;
    }

    private function escalateAssignment(RiderAssignment $assignment, $order): void
    {
        Cache::forget("rider_call:{$assignment->id}");

        $this->riderAssignment->expireStaleAssignments();

        Log::info('CallRiderReminder: Escalated assignment', [
            'assignment_id' => $assignment->id,
            'order_id' => $order->id,
            'rider_id' => $assignment->rider_id,
        ]);
    }
}
