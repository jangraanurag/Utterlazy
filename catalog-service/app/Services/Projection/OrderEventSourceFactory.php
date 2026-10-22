<?php

namespace App\Services\Projection;

use RuntimeException;

class OrderEventSourceFactory
{
    public function make(?string $transport = null): OrderEventSource
    {
        $transport = $transport ?: (string) config('services.projection.transport', 'redis');

        if ($transport === 'auto') {
            $transport = class_exists(\RdKafka\KafkaConsumer::class) ? 'kafka' : 'redis';
        }

        if ($transport === 'kafka') {
            return new KafkaOrderEventSource(
                (string) config('services.projection.kafka_brokers', 'kafka:9092'),
                (string) config('services.projection.kafka_topic', 'utterlazy.order.events.v1'),
                (string) config('services.projection.group_id', 'utterlazy-order-projection'),
            );
        }

        if ($transport !== 'redis') {
            throw new RuntimeException("Unsupported projection transport: {$transport}");
        }

        return new RedisOrderEventSource(
            (string) config('services.projection.redis_stream', 'outbox:order.events.v1'),
            (string) config('services.projection.redis_cursor', 'projection:cursor'),
        );
    }
}
