<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class EventPublisher
{
    /**
     * Publish an event to the shared event bus.
     * Dev/staging: Redis stream `outbox:<topic>` (same key catalog uses).
     * Production: Kafka topic `<prefix><topic>` when php-rdkafka is present.
     */
    public function publish(string $topic, array $data, ?string $partitionKey = null): void
    {
        $envelope = [
            'event_id' => (string) Str::uuid(),
            'event_type' => $data['event_type'] ?? $topic,
            'occurred_at' => now()->toIso8601String(),
            'city_id' => $data['city_id'] ?? null,
            'data' => $data,
        ];

        $driver = config('whatsapp.event_bus_driver', 'redis');

        if ($driver === 'kafka' && class_exists(\RdKafka\Producer::class)) {
            $this->publishKafka($topic, $partitionKey, $envelope);

            return;
        }

        Redis::xadd('outbox:'.$topic, '*', [
            'event_id' => $envelope['event_id'],
            'payload' => json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function publishKafka(string $topic, ?string $partitionKey, array $envelope): void
    {
        try {
            $conf = new \RdKafka\Conf;
            $conf->set('metadata.broker.list', config('whatsapp.kafka_brokers', 'kafka:9092'));
            $producer = new \RdKafka\Producer($conf);
            $kafkaTopic = $producer->newTopic(config('whatsapp.kafka_topic_prefix', 'utterlazy.').$topic);
            $payload = json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $kafkaTopic->produce(RD_KAFKA_PARTITION_UA, 0, $payload, $partitionKey ?? '');
            $producer->flush(5000);
        } catch (\Throwable $e) {
            Log::error('Kafka publish failed, falling back to Redis stream.', ['error' => $e->getMessage()]);
            Redis::xadd('outbox:'.$topic, '*', [
                'event_id' => $envelope['event_id'],
                'payload' => json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
        }
    }
}
