<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallQueue;
use App\Models\Order;
use App\Services\EnableXService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CallController extends Controller
{
    public function __construct(
        private EnableXService $enableX
    ) {}

    private function respondWithResult(?array $result, string $queuedMsg = 'Call queued — you will receive a call shortly.'): JsonResponse
    {
        if ($result) {
            return response()->json(['voice_id' => $result['voice_id'], 'queued' => false]);
        }

        $active = $this->enableX->activeCallCount();
        $max = $this->enableX->maxConcurrent();
        $queueSize = CallQueue::where('status', 'pending')->count();

        return response()->json([
            'queued' => true,
            'message' => $queuedMsg,
            'active_calls' => $active,
            'max_concurrent' => $max,
            'queue_position' => $queueSize,
        ], 202);
    }

    public function callCustomerToRider(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        if ($order->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        if (! $order->rider_id || ! $order->rider) {
            return response()->json(['error' => 'No rider assigned'], 400);
        }

        $result = $this->enableX->callWithMasking($user->phone, $order->rider->phone, [
            'name' => 'customer-to-rider',
            'owner_ref' => $order->id,
            'custom_data' => [
                'order_id' => $order->id,
                'call_type' => 'customer_to_rider',
            ],
        ]);

        return $this->respondWithResult($result);
    }

    public function callCustomerToRestaurant(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        if ($order->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $restaurantPhone = $order->restaurant?->phone;
        if (! $restaurantPhone) {
            return response()->json(['error' => 'Restaurant phone not available'], 400);
        }

        $result = $this->enableX->callDirect($restaurantPhone, [
            'name' => 'customer-to-restaurant',
            'owner_ref' => $order->id,
            'tts_text' => 'You have a call from a UtterLazy customer regarding order '.$order->id,
            'custom_data' => [
                'order_id' => $order->id,
                'call_type' => 'customer_to_restaurant',
            ],
        ]);

        return $this->respondWithResult($result, 'Calling the restaurant — please wait.');
    }

    public function callRiderToCustomer(Request $request, Order $order): JsonResponse
    {
        $rider = $request->user();
        if ($order->rider_id !== $rider->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $result = $this->enableX->callWithMasking($rider->phone, $order->user->phone, [
            'name' => 'rider-to-customer',
            'owner_ref' => $order->id,
            'custom_data' => [
                'order_id' => $order->id,
                'call_type' => 'rider_to_customer',
            ],
        ]);

        return $this->respondWithResult($result);
    }

    public function callRiderToRestaurant(Request $request, Order $order): JsonResponse
    {
        $rider = $request->user();
        if ($order->rider_id !== $rider->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $restaurantPhone = $order->restaurant?->phone;
        if (! $restaurantPhone) {
            return response()->json(['error' => 'Restaurant phone not available'], 400);
        }

        $result = $this->enableX->callDirect($restaurantPhone, [
            'name' => 'rider-to-restaurant',
            'owner_ref' => $order->id,
            'tts_text' => 'You have a call from a UtterLazy rider regarding order '.$order->id,
            'custom_data' => [
                'order_id' => $order->id,
                'call_type' => 'rider_to_restaurant',
            ],
        ]);

        return $this->respondWithResult($result, 'Calling the restaurant — please wait.');
    }

    public function callRestaurantToCustomer(Request $request, Order $order): JsonResponse
    {
        $restaurant = $request->user()->restaurant;
        if ($order->restaurant_id !== $restaurant?->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $restaurantPhone = $restaurant->phone;
        if (! $restaurantPhone) {
            return response()->json(['error' => 'Restaurant phone not available'], 400);
        }

        $result = $this->enableX->callWithMasking($restaurantPhone, $order->user->phone, [
            'name' => 'restaurant-to-customer',
            'owner_ref' => $order->id,
            'custom_data' => [
                'order_id' => $order->id,
                'call_type' => 'restaurant_to_customer',
            ],
        ]);

        return $this->respondWithResult($result);
    }

    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::info('EnableX webhook received', $payload);

        $state = $payload['state'] ?? '';
        $voiceId = $payload['voice_id'] ?? '';

        switch ($state) {
            case 'disconnected':
                Log::info('EnableX: Call ended', [
                    'voice_id' => $voiceId,
                    'duration' => $payload['recording_duration'] ?? null,
                    'reason' => $payload['disconnect_reason'] ?? null,
                ]);
                $this->enableX->releaseCallResources($voiceId);
                break;

            case 'failed':
                Log::warning('EnableX: Call/bridge failed', [
                    'voice_id' => $voiceId,
                ]);
                $this->enableX->releaseCallResources($voiceId);
                break;

            case 'bridged':
                Log::info('EnableX: Bridge established', [
                    'voice_id' => $voiceId,
                    'order_id' => $customData['order_id'] ?? null,
                    'call_type' => $customData['call_type'] ?? null,
                ]);
                break;
        }

        return response()->json(['status' => 'ok']);
    }
}
