<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CatalogClient
{
    private string $baseUrl;

    private string $serviceToken;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('whatsapp.catalog_service_url', 'http://catalog-service:8001'), '/');
        $this->serviceToken = config('whatsapp.service_token');
    }

    private function headers(array $extra = []): array
    {
        return array_merge([
            'Accept' => 'application/json',
            'X-Service-Token' => $this->serviceToken,
        ], $extra);
    }

    public function nearMe(float $lat, float $lng, int $limit = 5): array
    {
        $response = Http::withHeaders($this->headers())
            ->timeout(15)
            ->get("{$this->baseUrl}/api/restaurants", [
                'latitude' => $lat,
                'longitude' => $lng,
                'per_page' => $limit,
            ]);

        return $response->json() ?? [];
    }

    public function searchByName(string $query, int $limit = 5): array
    {
        $response = Http::withHeaders($this->headers())
            ->timeout(15)
            ->get("{$this->baseUrl}/api/search", ['q' => $query, 'per_page' => $limit]);

        return $response->json() ?? [];
    }

    public function menuItems(string $restaurantId, int $limit = 10): array
    {
        $response = Http::withHeaders($this->headers())
            ->timeout(15)
            ->get("{$this->baseUrl}/api/menu-items", ['restaurant_id' => $restaurantId, 'per_page' => $limit]);

        return $response->json() ?? [];
    }

    public function restaurant(string $restaurantId): array
    {
        $response = Http::withHeaders($this->headers())
            ->timeout(10)
            ->get("{$this->baseUrl}/api/restaurants/{$restaurantId}");

        return $response->json() ?? [];
    }

    public function recordImpression(array $placementIds, string $externalId): void
    {
        if (empty($placementIds)) {
            return;
        }

        try {
            Http::withHeaders($this->headers())
                ->timeout(5)
                ->post("{$this->baseUrl}/api/internal/wa-impression", [
                    'placement_ids' => array_values($placementIds),
                    'external_id' => $externalId,
                ]);
        } catch (\Throwable) {
            // Analytics must never block a conversation.
        }
    }

    public function recordClick(string $placementId, string $externalId): void
    {
        try {
            Http::withHeaders($this->headers())
                ->timeout(5)
                ->post("{$this->baseUrl}/api/internal/wa-click", [
                    'placement_id' => $placementId,
                    'external_id' => $externalId,
                ]);
        } catch (\Throwable) {
            // Charging retries on the next click; never block the customer.
        }
    }

    /**
     * Send an OTP to a 10-digit Indian mobile (customer account link).
     */
    public function requestOtp(string $phone): array
    {
        $response = Http::withHeaders($this->headers())->timeout(10)->acceptJson()
            ->post("{$this->baseUrl}/api/internal/otp/request", ['phone' => $phone]);

        // Rolling deploy: fall back to the public endpoint if the internal route is absent.
        if ($response->status() === 404) {
            $response = Http::timeout(10)->acceptJson()
                ->post("{$this->baseUrl}/api/auth/otp/request", ['phone' => $phone]);
        }

        return ['status' => $response->status(), 'body' => $response->json() ?? []];
    }

    /**
     * Verify the OTP and receive a short-lived customer token + user.
     */
    public function verifyOtp(string $phone, string $otp): array
    {
        $response = Http::withHeaders($this->headers())->timeout(10)->acceptJson()
            ->post("{$this->baseUrl}/api/internal/otp/verify", ['phone' => $phone, 'otp' => $otp]);

        if ($response->status() === 404) {
            $response = Http::timeout(10)->acceptJson()
                ->post("{$this->baseUrl}/api/auth/otp/verify", ['phone' => $phone, 'otp' => $otp]);
        }

        return ['status' => $response->status(), 'body' => $response->json() ?? []];
    }

    /**
     * Place an order through the SAME checkout API as app/web.
     * WhatsApp never writes orders directly.
     */
    public function checkout(string $userToken, array $payload, ?string $idempotencyKey = null): array
    {
        $headers = [
            'Authorization' => "Bearer {$userToken}",
            'Accept' => 'application/json',
        ];

        if ($idempotencyKey) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        $payload['channel'] = 'whatsapp';

        $response = Http::withHeaders($headers)
            ->timeout(30)
            ->post("{$this->baseUrl}/api/checkout", $payload);

        return [
            'status' => $response->status(),
            'body' => $response->json() ?? [],
        ];
    }

    public function order(string $userToken, string $orderId): array
    {
        $response = Http::withToken($userToken)
            ->timeout(15)
            ->get("{$this->baseUrl}/api/orders/{$orderId}");

        return ['status' => $response->status(), 'body' => $response->json() ?? []];
    }

    /**
     * Paid recommendation inventory. Organic search never calls this.
     */
    public function waReco(string $cityId, int $limit = 5): array
    {
        $response = Http::withHeaders($this->headers())
            ->timeout(15)
            ->get("{$this->baseUrl}/api/internal/wa-reco", ['city_id' => $cityId, 'limit' => $limit]);

        return $response->json() ?? [];
    }

    public static function idempotencyKeyFor(string $waId, string $sessionId): string
    {
        return 'wa-'.Str::limit(sha1($waId.'|'.$sessionId), 48, '');
    }
}
