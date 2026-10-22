<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;

class FcmNotificationService
{
    private $messaging;

    public function __construct()
    {
        $this->messaging = app('firebase.messaging');
    }

    /**
     * Register or update a device token for a user/vendor/rider.
     */
    public function registerToken(Model $user, string $token, string $platform, string $appType, ?string $deviceName = null): void
    {
        DeviceToken::updateOrCreate(
            [
                'tokenable_type' => get_class($user),
                'tokenable_id' => $user->id,
                'app_type' => $appType,
            ],
            [
                'token' => $token,
                'platform' => $platform,
                'device_name' => $deviceName,
                'last_used_at' => now(),
            ]
        );
    }

    /**
     * Remove a device token.
     */
    public function unregisterToken(string $token): void
    {
        DeviceToken::where('token', $token)->delete();
    }

    /**
     * Send a new order notification to vendor(s).
     * Uses DATA-ONLY payload (no "notification" key) so Android OS
     * triggers the background handler / native service even when app is killed.
     */
    public function notifyNewOrder(array $tokens, array $orderData): void
    {
        $message = CloudMessage::new()
            ->withAndroidConfig(
                \Kreait\Firebase\Messaging\AndroidConfig::create()
                    ->priority('high')
            )
            ->withData([
                'event' => 'new_order',
                'order_id' => (string) ($orderData['id'] ?? ''),
                'grand_total' => (string) ($orderData['grand_total'] ?? '0'),
                'restaurant_name' => (string) ($orderData['restaurant_name'] ?? ''),
                'delivery_address_text' => (string) ($orderData['delivery_address_text'] ?? ''),
                'screen' => 'incoming_order',
            ]);

        $this->sendToTokens($tokens, $message, 'new_order');
    }

    /**
     * Send a rider assignment notification.
     */
    public function notifyRiderAssigned(array $tokens, array $assignmentData): void
    {
        $message = CloudMessage::new()
            ->withAndroidConfig(
                \Kreait\Firebase\Messaging\AndroidConfig::create()
                    ->priority('high')
            )
            ->withData([
                'event' => 'rider.assigned',
                'order_id' => (string) ($assignmentData['order_id'] ?? ''),
                'delivery_fee' => (string) ($assignmentData['delivery_fee'] ?? '45'),
                'rider_name' => (string) ($assignmentData['rider_name'] ?? ''),
                'screen' => 'assignment_detail',
            ]);

        $this->sendToTokens($tokens, $message, 'rider.assigned');
    }

    /**
     * Send an order status change notification.
     */
    public function notifyOrderStatusChanged(array $tokens, array $statusData): void
    {
        $message = CloudMessage::new()
            ->withAndroidConfig(
                \Kreait\Firebase\Messaging\AndroidConfig::create()
                    ->priority('high')
            )
            ->withData([
                'event' => 'status.changed',
                'order_id' => (string) ($statusData['order_id'] ?? ''),
                'status' => (string) ($statusData['status'] ?? ''),
                'screen' => 'order_detail',
            ]);

        $this->sendToTokens($tokens, $message, 'status.changed');
    }

    /**
     * Send a data-only FCM message to multiple tokens.
     * This is the GOLDEN RULE for killed-app delivery:
     * - NO "notification" key in payload
     * - android.priority = "high"
     * - data-only payload triggers background/native handler
     */
    private function sendToTokens(array $tokens, CloudMessage $message, string $event): void
    {
        if (empty($tokens)) {
            return;
        }

        try {
            $report = $this->messaging->sendEach($tokens, $message);

            if ($report->hasFailure()) {
                $failures = $report->failures();
                foreach ($failures as $failure) {
                    $reason = $failure->error()->getMessage();
                    Log::warning("FCM delivery failed for token: {$reason}");

                    // Remove invalid tokens (UNREGISTERED, INVALID_ARGUMENT)
                    if (str_contains($reason, 'UNREGISTERED') || str_contains($reason, 'INVALID_ARGUMENT')) {
                        DeviceToken::where('token', $failure->target())->delete();
                    }
                }
            }

            Log::info("FCM '{$event}' sent: {$report->successes()->count()} success, {$report->failures()->count()} failures");
        } catch (\Exception $e) {
            Log::error("FCM sendEach failed: {$e->getMessage()}");
        }
    }

    /**
     * Get all device tokens for a given app type and optional user IDs.
     */
    public function getTokensForUsers(string $appType, array $userIds): array
    {
        return DeviceToken::where('app_type', $appType)
            ->whereIn('tokenable_id', $userIds)
            ->pluck('token')
            ->toArray();
    }

    /**
     * Get all device tokens for a specific user across all their app types.
     */
    public function getTokensForUser(Model $user, ?string $appType = null): array
    {
        $query = DeviceToken::where('tokenable_type', get_class($user))
            ->where('tokenable_id', $user->id);

        if ($appType) {
            $query->where('app_type', $appType);
        }

        return $query->pluck('token')->toArray();
    }
}
