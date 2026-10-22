<?php

namespace App\Services;

use App\Models\Outbox;
use App\Services\Outbox\KafkaTransport;
use App\Services\Outbox\OutboxTransport;
use App\Services\Outbox\RedisStreamTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OutboxPublisher
{
    public const TOPIC_MAP = [
        'order' => 'order.events.v1',
        'payment' => 'payment.events.v1',
        'wa_placement' => 'ads.placement.v1',
        'support_ticket' => 'support.ticket.v1',
        'ops_incident' => 'ops.incident.v1',
        'safety_sos' => 'safety.sos.v1',
        'whatsapp_inbound' => 'whatsapp.inbound.v1',
        'whatsapp_outbound' => 'whatsapp.outbound.v1',
    ];

    public function enqueue(
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        array $payload,
        ?string $cityId = null,
    ): Outbox {
        return Outbox::create([
            'event_id' => (string) Str::uuid(),
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'event_type' => $eventType,
            'payload' => $payload,
            'city_id' => $cityId,
            'occurred_at' => now(),
        ]);
    }

    public function topicFor(string $aggregateType): string
    {
        return self::TOPIC_MAP[$aggregateType] ?? ($aggregateType.'.events.v1');
    }

    private ?OutboxTransport $overrideTransport = null;

    /**
     * Inject a transport (tests / custom brokers) without touching config.
     */
    public function useTransport(OutboxTransport $transport): static
    {
        $this->overrideTransport = $transport;

        return $this;
    }

    public function publishPending(?int $limit = null, ?int $maxRetries = null): array
    {
        $configured = function (string $key, int $default): int {
            try {
                return (int) config($key, $default);
            } catch (\Throwable) {
                return $default;
            }
        };

        $limit ??= $configured('services.outbox.batch_size', 100);
        $maxRetries ??= $configured('services.outbox.max_retries', 5);

        $rows = Outbox::whereNull('published_at')
            ->where('attempts', '<', $maxRetries)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $published = 0;
        $failed = 0;

        foreach ($rows as $row) {
            try {
                $envelope = [
                    'event_id' => $row->event_id,
                    'event_type' => $row->event_type,
                    'occurred_at' => optional($row->occurred_at)->toIso8601String(),
                    'city_id' => $row->city_id,
                    'data' => $row->payload,
                ];

                $topic = $this->topicFor($row->aggregate_type);

                $this->transport()->publish($topic, $row->city_id, $envelope);

                $row->markPublished();
                $published++;
            } catch (\Throwable $e) {
                $row->markFailed($e->getMessage());

                if ($row->attempts >= $maxRetries) {
                    $this->sendToDlq($row);
                }

                $this->safeLog('warning', "Outbox publish failed for {$row->event_id}: {$e->getMessage()}");
                $failed++;
            }
        }

        return ['published' => $published, 'failed' => $failed, 'pending' => Outbox::whereNull('published_at')->count()];
    }

    public function transport(): OutboxTransport
    {
        if ($this->overrideTransport) {
            return $this->overrideTransport;
        }

        try {
            $driver = config('services.outbox.transport', 'redis');
        } catch (\Throwable) {
            $driver = 'redis';
        }

        return $driver === 'kafka' ? new KafkaTransport : new RedisStreamTransport;
    }

    private function sendToDlq(Outbox $row): void
    {
        try {
            $envelope = [
                'event_id' => $row->event_id,
                'event_type' => $row->event_type,
                'occurred_at' => optional($row->occurred_at)->toIso8601String(),
                'city_id' => $row->city_id,
                'data' => $row->payload,
                'dlq_reason' => $row->last_error,
            ];

            $topic = $this->topicFor($row->aggregate_type);

            if ($this->transport() instanceof KafkaTransport) {
                $this->transport()->publishToDlq($topic, $envelope);
            } else {
                $this->transport()->publishToDlq($topic, $envelope);
            }
        } catch (\Throwable $e) {
            $this->safeLog('error', "DLQ publish failed for {$row->event_id}: {$e->getMessage()}");
        }
    }

    private function safeLog(string $level, string $message): void
    {
        try {
            Log::$level($message);
        } catch (\Throwable) {
            // Standalone test/CLI context without a facade root.
        }
    }

    public function oldestPendingAgeSeconds(): ?int
    {
        $oldest = Outbox::whereNull('published_at')->orderBy('occurred_at')->first();

        return $oldest ? (int) $oldest->occurred_at->diffInSeconds(now()) : null;
    }

    public function purgeProcessed(int $olderThanDays = 7): int
    {
        return DB::table('outbox')
            ->whereNotNull('published_at')
            ->where('published_at', '<', now()->subDays($olderThanDays))
            ->delete();
    }
}
