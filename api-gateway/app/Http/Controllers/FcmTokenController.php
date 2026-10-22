<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use App\Services\FcmNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class FcmTokenController extends Controller
{
    public function __construct(
        private FcmNotificationService $fcmService
    ) {}

    /**
     * Register or refresh an FCM device token.
     * Called by Flutter app after login and on token refresh.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'platform' => 'required|in:android,ios',
            'app_type' => 'required|in:customer,restaurant,rider',
            'device_name' => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Store locally in api-gateway's device_tokens table
        $this->fcmService->registerToken(
            $user,
            $request->input('token'),
            $request->input('platform'),
            $request->input('app_type'),
            $request->input('device_name')
        );

        // Also forward to catalog-service so its event listeners can find the token
        $this->forwardToCatalogService($request);

        return response()->json(['message' => 'Token registered successfully']);
    }

    /**
     * Unregister an FCM device token (e.g., on logout).
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $this->fcmService->unregisterToken($request->input('token'));

        // Also remove from catalog-service
        $this->forwardDeleteToCatalogService($request);

        return response()->json(['message' => 'Token unregistered successfully']);
    }

    private function forwardToCatalogService(Request $request): void
    {
        $catalogUrl = env('CATALOG_SERVICE_URL', 'http://127.0.0.1:8001/api');
        $token = $request->bearerToken();

        try {
            Http::withToken($token)
                ->timeout(5)
                ->post(rtrim($catalogUrl, '/') . '/fcm/token', [
                    'token'       => $request->input('token'),
                    'platform'    => $request->input('platform'),
                    'app_type'    => $request->input('app_type'),
                    'device_name' => $request->input('device_name'),
                ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('FCM token forward to catalog failed: ' . $e->getMessage());
        }
    }

    private function forwardDeleteToCatalogService(Request $request): void
    {
        $catalogUrl = env('CATALOG_SERVICE_URL', 'http://127.0.0.1:8001/api');
        $token = $request->bearerToken();

        try {
            Http::withToken($token)
                ->timeout(5)
                ->delete(rtrim($catalogUrl, '/') . '/fcm/token', [
                    'token' => $request->input('token'),
                ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('FCM token delete forward to catalog failed: ' . $e->getMessage());
        }
    }
}
