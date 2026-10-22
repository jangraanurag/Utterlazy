<?php

namespace App\Services\Projection;

interface OrderEventSource
{
    /**
     * Fetch up to $limit envelopes.
     *
     * @return array<int, array{id:string, envelope:array, meta:array}>
     */
    public function poll(int $limit, int $timeoutMs): array;

    /** Acknowledge successful projection (advance cursor / commit offset). */
    public function ack(array $record): void;

    public function close(): void;
}
