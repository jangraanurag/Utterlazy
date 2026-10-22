<?php

namespace App\Console\Commands;

use App\Listeners\OrderEventListener;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ConsumeEvents extends Command
{
    protected $signature = 'events:consume
                            {--once : Read one batch and exit}
                            {--batch=50 : Max messages per stream read}
                            {--sleep-ms=3000 : Block time per read}';

    protected $description = 'Consume order/payment events and send WhatsApp utility templates';

    private const STREAMS = [
        'outbox:order.events.v1',
        'outbox:payment.events.v1',
    ];

    private const CURSOR_KEY = 'wa:consumer:last_ids';

    public function handle(OrderEventListener $listener): int
    {
        $batch = (int) $this->option('batch');
        $sleepMs = (int) $this->option('sleep-ms');

        do {
            $streams = [];
            foreach (self::STREAMS as $stream) {
                $streams[$stream] = $this->cursorFor($stream);
            }

            $result = Redis::xread($streams, $batch, $sleepMs);

            if (! $result) {
                if ($this->option('once')) {
                    break;
                }

                continue;
            }

            foreach ($result as $stream => $entries) {
                foreach ($entries as $entryId => $fields) {
                    $this->processEntry($stream, (string) $entryId, $fields, $listener);
                    $this->saveCursor($stream, (string) $entryId);
                }
            }

            if ($this->option('once')) {
                break;
            }
        } while (true);

        return self::SUCCESS;
    }

    private function processEntry(string $stream, string $entryId, array $fields, OrderEventListener $listener): void
    {
        $payload = $fields['payload'] ?? null;
        if (! $payload) {
            return;
        }

        $envelope = json_decode($payload, true);
        if (! is_array($envelope)) {
            Log::warning('Invalid event payload', compact('stream', 'entryId'));

            return;
        }

        $eventId = $envelope['event_id'] ?? null;
        if (! $eventId) {
            return;
        }

        $inserted = DB::table('wa_processed_events')->insertOrIgnore([
            'event_id' => $eventId,
            'consumer' => 'whatsapp-service',
            'processed_at' => now(),
        ]);

        if (! $inserted) {
            return; // Already processed.
        }

        try {
            $listener->handle($envelope);
        } catch (\Throwable $e) {
            Log::error('Event handling failed.', [
                'event_id' => $eventId,
                'stream' => $stream,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function cursorFor(string $stream): string
    {
        $cursors = json_decode(Redis::get(self::CURSOR_KEY) ?: '[]', true) ?: [];

        return $cursors[$stream] ?? '0-0';
    }

    private function saveCursor(string $stream, string $entryId): void
    {
        $cursors = json_decode(Redis::get(self::CURSOR_KEY) ?: '[]', true) ?: [];
        $cursors[$stream] = $entryId;
        Redis::set(self::CURSOR_KEY, json_encode($cursors));
    }
}
