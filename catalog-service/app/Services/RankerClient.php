<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client for the Wave 3 organic ranker.
 *
 * Sponsored inventory is never sent to this client; the ranker also rejects it.
 * Any failure returns null so the caller can fall back to the PHP path.
 */
class RankerClient
{
    public function enabled(): bool
    {
        return (bool) config('services.ranker.enabled', false)
            && (bool) config('services.ranker.url');
    }

    /**
     * @param array<int, array<string, mixed>> $candidates
     * @param array<string, mixed> $options
     * @return array{model_version:string, items:array<int, array<string, mixed>>}|null
     */
    public function rank(array $candidates, array $options = []): ?array
    {
        if (! $this->enabled() || empty($candidates)) {
            return null;
        }

        try {
            $response = Http::timeout(3)
                ->acceptJson()
                ->post(rtrim((string) config('services.ranker.url'), '/').'/rank', [
                    'candidates' => array_values($candidates),
                    'options' => $options,
                ]);

            if ($response->failed()) {
                Log::warning('Ranker rejected request.', ['status' => $response->status()]);

                return null;
            }

            $body = $response->json();

            if (! is_array($body) || ! isset($body['items']) || ! is_array($body['items'])) {
                return null;
            }

            return $body;
        } catch (\Throwable $e) {
            Log::warning('Ranker unreachable: '.$e->getMessage());

            return null;
        }
    }
}
