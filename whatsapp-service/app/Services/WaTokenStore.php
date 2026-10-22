<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Short-lived customer API token for WhatsApp conversations.
 *
 * Kept in Redis (not the session JSON) so a DB leak does not expose tokens.
 * 30-minute TTL; the customer re-links with OTP when it expires.
 */
class WaTokenStore
{
    private const TTL_MINUTES = 30;

    public function put(string $waId, string $token): void
    {
        Cache::put($this->key($waId), $token, now()->addMinutes(self::TTL_MINUTES));
    }

    public function get(string $waId): ?string
    {
        $token = Cache::get($this->key($waId));

        return is_string($token) && $token !== '' ? $token : null;
    }

    public function forget(string $waId): void
    {
        Cache::forget($this->key($waId));
    }

    private function key(string $waId): string
    {
        return 'wa:token:'.$waId;
    }
}
