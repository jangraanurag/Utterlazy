<?php

namespace App\Services;

use App\Models\CallQueue;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EnableXService
{
    private string $baseUrl = 'https://api.enablex.io/voice/v1';

    private string $appId = '';

    private string $appKey = '';

    private const CACHE_ACTIVE_KEY = 'enablex:active_calls';

    public function __construct()
    {
        $this->appId = config('services.enablex.app_id') ?? '';
        $this->appKey = config('services.enablex.app_key') ?? '';
    }

    public function maxConcurrent(): int
    {
        return (int) config('services.enablex.max_concurrent', 5);
    }

    public function allVirtualNumbers(): array
    {
        $nums = array_values(array_filter(config('services.enablex.virtual_numbers', [])));

        return $nums ?: ['enablex'];
    }

    /** Pick a number from the pool (cyclic index by active call count for variety). */
    public function pickNumber(int $offset = 0): string
    {
        $numbers = $this->allVirtualNumbers();

        return $numbers[(int) (Cache::get(self::CACHE_ACTIVE_KEY, 0) + $offset) % count($numbers)];
    }

    public function activeCallCount(): int
    {
        return (int) Cache::get(self::CACHE_ACTIVE_KEY, 0);
    }

    public function canAcceptCall(): bool
    {
        return $this->activeCallCount() < $this->maxConcurrent();
    }

    public function incrementActiveCalls(): void
    {
        Cache::increment(self::CACHE_ACTIVE_KEY);
    }

    public function decrementActiveCalls(): void
    {
        $val = Cache::decrement(self::CACHE_ACTIVE_KEY);
        if ($val < 0) {
            Cache::put(self::CACHE_ACTIVE_KEY, 0);
        }
    }

    /* ─── HTTP Client ─── */

    private function client(): PendingRequest
    {
        return Http::withBasicAuth($this->appId, $this->appKey)
            ->timeout(15)
            ->throw();
    }

    /* ─── Core API Calls ─── */

    public function makeCall(string $from, string $to, array $options = []): ?array
    {
        $payload = array_merge([
            'name' => $options['name'] ?? 'utterlazy-call',
            'owner_ref' => $options['owner_ref'] ?? '',
            'from' => $from,
            'to' => $to,
            'event_url' => config('services.enablex.webhook_url'),
        ], $options['extra'] ?? []);

        if (isset($options['play_tts'])) {
            $payload['action_on_connect']['play'] = [
                'text' => $options['play_tts']['text'],
                'language' => $options['play_tts']['language'] ?? 'hi-IN',
                'voice' => $options['play_tts']['voice'] ?? 'female',
            ];
        }

        if (isset($options['connect'])) {
            $payload['action_on_connect']['connect'] = [
                'from' => $options['connect']['from'],
                'to' => $options['connect']['to'],
            ];
        }

        if (isset($options['custom_data'])) {
            $payload['custom_data'] = $options['custom_data'];
        }

        try {
            $response = $this->client()->post("{$this->baseUrl}/call", $payload);
            $data = $response->json();

            if (($data['state'] ?? '') === 'initiated') {
                Log::info('EnableX: Call initiated', [
                    'voice_id' => $data['voice_id'] ?? null,
                    'from' => $from,
                    'to' => $to,
                ]);

                return $data;
            }

            Log::error('EnableX: Call initiation failed', [
                'response' => $data,
                'from' => $from,
                'to' => $to,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('EnableX: Call exception: '.$e->getMessage(), [
                'from' => $from,
                'to' => $to,
            ]);

            return null;
        }
    }

    public function hangup(string $voiceId): bool
    {
        try {
            $this->client()->delete("{$this->baseUrl}/call/{$voiceId}");

            return true;
        } catch (\Exception $e) {
            Log::error('EnableX: Hangup failed: '.$e->getMessage(), ['voice_id' => $voiceId]);

            return false;
        }
    }

    /* ─── High-Level: Call With Masking ─── */

    public function callWithMasking(string $party1Phone, string $party2Phone, array $options = []): ?array
    {
        if (! $this->canAcceptCall()) {
            Log::warning('EnableX: At max concurrent calls, queueing masked call');
            $this->queueCall($party1Phone, $party2Phone, $options);

            return null;
        }

        $fromNumber = $this->pickNumber(0);
        $bridgeNumber = $this->pickNumber(1);

        $this->incrementActiveCalls();

        $result = $this->makeCall($fromNumber, $party1Phone, [
            'name' => $options['name'] ?? 'utterlazy-masked-call',
            'owner_ref' => $options['owner_ref'] ?? '',
            'play_tts' => $options['play_tts'] ?? null,
            'connect' => [
                'from' => $bridgeNumber,
                'to' => $party2Phone,
            ],
            'custom_data' => array_merge($options['custom_data'] ?? [], [
                'numbers_used' => [$fromNumber, $bridgeNumber],
            ]),
            'extra' => $options['extra'] ?? [],
        ]);

        if (! $result) {
            $this->decrementActiveCalls();
        }

        return $result;
    }

    /* ─── High-Level: Direct Call (no masking, e.g. restaurant reminders) ─── */

    public function callDirect(string $to, array $options = []): ?array
    {
        if (! $this->canAcceptCall()) {
            Log::warning('EnableX: At max concurrent calls, queueing direct call');
            $this->queueCall(null, $to, $options);

            return null;
        }

        $fromNumber = $this->pickNumber(0);

        $this->incrementActiveCalls();

        $result = $this->makeCall($fromNumber, $to, [
            'name' => $options['name'] ?? 'utterlazy-direct-call',
            'owner_ref' => $options['owner_ref'] ?? '',
            'play_tts' => $options['play_tts'] ?? [
                'text' => $options['tts_text'] ?? 'You have a new order from UtterLazy.',
                'language' => $options['tts_language'] ?? 'hi-IN',
                'voice' => $options['tts_voice'] ?? 'female',
            ],
            'custom_data' => array_merge($options['custom_data'] ?? [], [
                'numbers_used' => [$fromNumber],
            ]),
            'extra' => $options['extra'] ?? [],
        ]);

        if (! $result) {
            $this->decrementActiveCalls();
        }

        return $result;
    }

    /* ─── Call Queue ─── */

    public function queueCall(?string $party1Phone, string $party2Phone, array $options = []): void
    {
        $callType = $options['custom_data']['call_type'] ?? 'general';
        $orderId = $options['custom_data']['order_id'] ?? null;

        CallQueue::create([
            'order_id' => $orderId,
            'call_type' => $callType,
            'from_phone' => $party1Phone,
            'to_phone' => $party2Phone,
            'payload' => $options,
            'status' => 'pending',
            'priority' => $options['priority'] ?? 5,
            'attempts' => 0,
            'max_attempts' => $options['max_attempts'] ?? 3,
            'scheduled_at' => now(),
        ]);

        Log::info('EnableX: Call queued', [
            'order_id' => $orderId,
            'call_type' => $callType,
            'queue_size' => CallQueue::where('status', 'pending')->count(),
        ]);
    }

    /* ─── Release resources when call ends (called from webhook) ─── */

    public function releaseCallResources(string $voiceId): void
    {
        $this->decrementActiveCalls();

        Log::info('EnableX: Call resources released', [
            'voice_id' => $voiceId,
            'active_calls' => $this->activeCallCount(),
        ]);
    }
}
