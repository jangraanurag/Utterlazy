<?php

namespace App\Domain\Conversation;

use App\Models\WaSession;

class WaContext
{
    public function __construct(public readonly WaSession $session)
    {
    }

    public function state(): string
    {
        return $this->session->state;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->session->context[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $context = $this->session->context ?? [];
        $context[$key] = $value;
        $this->session->update(['context' => $context]);
    }

    public function transition(string $state): void
    {
        $this->session->update([
            'state' => $state,
            'expires_at' => now()->addMinutes((int) config('whatsapp.session_ttl_minutes', 30)),
        ]);
    }
}
