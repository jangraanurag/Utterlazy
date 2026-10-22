<?php

namespace App\Services;

use App\Models\FcmToken;
use App\Models\Restaurant;
use App\Models\Rider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    private string $projectId;

    private string $serviceAccountPath;

    public function __construct()
    {
        $this->projectId = config('services.fcm.project_id') ?? '';
        $this->serviceAccountPath = config('services.fcm.service_account_path') ?? '';
    }

    public function sendToDevice(string $token, array $data, ?string $title = null, ?string $body = null): bool
    {
        if (empty($this->projectId) || empty($this->serviceAccountPath)) {
            return false;
        }

        try {
            $accessToken = $this->getAccessToken();
            if (empty($accessToken)) {
                Log::error('FCM: Cannot send — no access token');

                return false;
            }

            $data['_title'] = $title ?? 'UtterLazy Update';
            $data['_body'] = $body ?? 'Tap to view';
            $appType = $data['app_type'] ?? 'general';
            $orderId = $data['order_id'] ?? $data['id'] ?? 'unknown';

            // Always send BOTH notification + data for all app types.
            // Notification wakes the device even when app is killed.
            // Data payload tells the app what to do when opened.
            $message = [
                'token' => $token,
                'data' => array_map('strval', $data),
                'notification' => [
                    'title' => $title ?? 'UtterLazy Update',
                    'body' => $body ?? 'Tap to view',
                ],
                'android' => [
                    'ttl' => '86400s',
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => $this->getChannelId($appType),
                        'notification_priority' => 'PRIORITY_HIGH',
                        'default_sound' => true,
                        'default_vibrate_timings' => true,
                        'visibility' => 'public',
                        'notification_count' => 1,
                        'tag' => $orderId,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'sticky' => false,
                    ],
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                    'payload' => [
                        'aps' => [
                            'alert' => [
                                'title' => $title ?? 'UtterLazy Update',
                                'body' => $body ?? 'Tap to view',
                            ],
                            'sound' => 'default',
                            'badge' => 1,
                            'content-available' => 1,
                        ],
                    ],
                ],
            ];

            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->post(
                    "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send",
                    ['message' => $message]
                );

            if ($response->successful()) {
                return true;
            }

            Log::error('FCM send failed', [
                'token' => substr($token, 0, 20).'...',
                'status' => $response->status(),
            ]);

            if ($response->status() === 404 || str_contains($response->body(), 'UNREGISTERED')) {
                FcmToken::where('token', $token)->delete();
            }

            return false;
        } catch (\Exception $e) {
            Log::error('FCM send exception: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Send to a user (customer/rider) by User model
     */
    public function sendToUser($user, string $appType, array $data, ?string $title = null, ?string $body = null): int
    {
        $tokens = FcmToken::where('user_id', $user->id)
            ->forAppType($appType)
            ->pluck('token');

        $sent = 0;
        foreach ($tokens as $token) {
            $data['app_type'] = $appType;
            if ($this->sendToDevice($token, $data, $title, $body)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Send to a restaurant (vendor) by Restaurant model
     */
    public function sendToRestaurant(Restaurant $restaurant, array $data, ?string $title = null, ?string $body = null): int
    {
        $tokens = FcmToken::where('restaurant_id', $restaurant->id)
            ->forAppType('restaurant')
            ->pluck('token');

        $sent = 0;
        foreach ($tokens as $token) {
            $data['app_type'] = 'restaurant';
            if ($this->sendToDevice($token, $data, $title, $body)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Send to a rider by Rider model
     */
    public function sendToRider(Rider $rider, array $data, ?string $title = null, ?string $body = null): int
    {
        $tokens = FcmToken::where('rider_id', $rider->id)
            ->forAppType('rider')
            ->pluck('token');

        $sent = 0;
        foreach ($tokens as $token) {
            $data['app_type'] = 'rider';
            if ($this->sendToDevice($token, $data, $title, $body)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Send to multiple user IDs
     */
    public function sendToMultiple(array $userIds, string $appType, array $data, ?string $title = null, ?string $body = null): int
    {
        $tokens = FcmToken::whereIn('user_id', $userIds)
            ->forAppType($appType)
            ->pluck('token');

        $sent = 0;
        foreach ($tokens as $token) {
            $data['app_type'] = $appType;
            if ($this->sendToDevice($token, $data, $title, $body)) {
                $sent++;
            }
        }

        return $sent;
    }

    private function getAccessToken(): ?string
    {
        if (empty($this->serviceAccountPath) || ! file_exists($this->serviceAccountPath)) {
            Log::error('FCM: Service account file not found at path: '.$this->serviceAccountPath);

            return null;
        }

        $serviceAccount = json_decode(
            file_get_contents($this->serviceAccountPath),
            true
        );

        if (empty($serviceAccount['client_email']) || empty($serviceAccount['private_key'])) {
            Log::error('FCM: Invalid service account file — missing client_email or private_key');

            return null;
        }

        $now = time();
        $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'iss' => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signatureInput = "$header.$payload";
        $signed = openssl_sign($signatureInput, $signature, $serviceAccount['private_key'], 'SHA256');
        if (! $signed) {
            Log::error('FCM: Failed to sign JWT with private key');

            return null;
        }

        $jwt = "$signatureInput.".base64_encode($signature);

        try {
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (! $response->successful()) {
                Log::error('FCM: OAuth2 token exchange failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $accessToken = $response->json('access_token');
            if (empty($accessToken)) {
                Log::error('FCM: OAuth2 response missing access_token', [
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $accessToken;
        } catch (\Exception $e) {
            Log::error('FCM: OAuth2 token exchange exception: '.$e->getMessage());

            return null;
        }
    }

    private function getChannelId(string $appType): string
    {
        return match ($appType) {
            'restaurant' => 'vendor_orders_channel',
            'rider' => 'rider_orders_channel',
            default => 'general',
        };
    }
}
