<?php

namespace App\Services\Outbox;

use Illuminate\Support\Facades\Redis;

class RedisStreamTransport implements OutboxTransport
{
    public function publish(string $topic, ?string $partitionKey, array $envelope): void
    {
        $streamKey = 'outbox:'.$topic;

        Redis::xadd($streamKey, '*', [
            'event_id' => $envelope['event_id'] ?? '',
            'payload' => json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function publishToDlq(string $topic, array $envelope): void
    {
        $this->publish($topic.'.dlq', null, $envelope);
    }
}
