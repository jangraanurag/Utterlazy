<?php

namespace App\Console\Commands;

use App\Models\CallQueue;
use App\Services\EnableXService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessCallQueue extends Command
{
    protected $signature = 'calls:process-queue';

    protected $description = 'Process pending calls from the queue when capacity is available';

    private const BATCH_SIZE = 3;

    public function handle(EnableXService $enableX): int
    {
        $processed = 0;

        while ($processed < self::BATCH_SIZE && $enableX->canAcceptCall()) {
            $entry = CallQueue::pending()->byPriority()->lockForUpdate()->first();

            if (! $entry) {
                break;
            }

            $entry->update(['status' => 'processing']);

            $success = $this->dispatchQueuedCall($enableX, $entry);

            if ($success) {
                $entry->update(['status' => 'completed', 'attempts' => $entry->attempts + 1]);
                $processed++;
            } else {
                $entry->increment('attempts');
                $attempts = $entry->fresh()->attempts;

                if ($attempts >= $entry->max_attempts) {
                    $entry->update([
                        'status' => 'failed',
                        'error' => 'Max attempts reached',
                    ]);
                    Log::warning('CallQueue: Max attempts reached', ['id' => $entry->id]);
                } else {
                    $entry->update([
                        'status' => 'pending',
                        'scheduled_at' => now()->addSeconds(30),
                    ]);
                }
            }
        }

        if ($processed > 0) {
            Log::info("ProcessCallQueue: Dispatched {$processed} queued calls");
        }

        return Command::SUCCESS;
    }

    private function dispatchQueuedCall(EnableXService $enableX, CallQueue $entry): bool
    {
        $payload = $entry->payload ?? [];
        $callType = $entry->call_type;

        $isMasked = in_array($callType, [
            'customer_to_rider',
            'rider_to_customer',
            'restaurant_to_customer',
        ]);

        $result = $isMasked
            ? $enableX->callWithMasking($entry->from_phone, $entry->to_phone, $payload)
            : $enableX->callDirect($entry->to_phone, $payload);

        return $result !== null;
    }
}
