<?php

namespace App\Services\Outbox;

interface OutboxTransport
{
    public function publish(string $topic, ?string $partitionKey, array $envelope): void;
}
