<?php

namespace App\Services\Projection;

use Illuminate\Support\Facades\Redis;

class RedisOrderEventSource implements OrderEventSource
{
    public function __construct(
        private readonly string $streamKey,
        private readonly string $cursorKey,
    ) {
    }

    public function poll(int $limit, int $timeoutMs): array
    {
        $cursor = Redis::get($this->cursorKey) ?: '0-0';
        $result = Redis::xread([$this->streamKey => $cursor], $limit, $timeoutMs);

        if (! $result) {
            return [];
        }

        $records = [];

        foreach ($result as $entries) {
            foreach ($entries as $entryId => $fields) {
                $payload = $fields['payload'] ?? null;
                $envelope = is_string($payload) ? json_decode($payload, true) : null;

                if (! is_array($envelope)) {
                    $records[] = ['id' => (string) $entryId, 'envelope' => [], 'meta' => []];
                    continue;
                }

                $records[] = [
                    'id' => (string) $entryId,
                    'envelope' => $envelope,
                    'meta' => ['stream' => $this->streamKey, 'entry' => (string) $entryId],
                ];
            }
        }

        return $records;
    }

    public function ack(array $record): void
    {
        Redis::set($this->cursorKey, (string) $record['id']);
    }

    public function close(): void
    {
        // Nothing to close.
    }
}
