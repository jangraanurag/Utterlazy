<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppOpsClient
{
    private ?string $baseUrl;

    private ?string $token;

    public function __construct()
    {
        $this->baseUrl = config('services.whatsapp_service_url');
        $this->token = config('services.internal_token');
    }

    public function sessions(array $filters = []): array
    {
        if (! $this->baseUrl) {
            return ['sessions' => [], 'available' => false];
        }

        try {
            $response = Http::withHeaders(['X-Service-Token' => $this->token])
                ->timeout(10)
                ->get(rtrim($this->baseUrl, '/').'/api/internal/sessions', $filters);

            if ($response->failed()) {
                return ['sessions' => [], 'available' => false, 'error' => 'WhatsApp service unavailable.'];
            }

            return $response->json() + ['available' => true];
        } catch (\Throwable $e) {
            Log::warning('WhatsAppOpsClient::sessions failed: '.$e->getMessage());

            return ['sessions' => [], 'available' => false, 'error' => 'WhatsApp service unreachable.'];
        }
    }

    public function assign(string $waId, string $assigneeId): array
    {
        if (! $this->baseUrl) {
            return ['assigned' => false, 'error' => 'WhatsApp service URL not configured.'];
        }

        try {
            $response = Http::withHeaders(['X-Service-Token' => $this->token])
                ->timeout(10)
                ->post(rtrim($this->baseUrl, '/')."/api/internal/sessions/{$waId}/assign", [
                    'assignee_id' => $assigneeId,
                ]);

            return $response->json() ?? ['assigned' => false];
        } catch (\Throwable $e) {
            Log::warning('WhatsAppOpsClient::assign failed: '.$e->getMessage());

            return ['assigned' => false, 'error' => 'WhatsApp service unreachable.'];
        }
    }
}
