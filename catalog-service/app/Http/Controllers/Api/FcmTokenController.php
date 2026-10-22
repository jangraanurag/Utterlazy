<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FcmToken;
use App\Models\Restaurant;
use App\Models\Rider;
use Illuminate\Http\Request;

class FcmTokenController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string|max:512',
            'platform' => 'required|in:android,ios,web',
            'app_type' => 'required|in:customer,restaurant,rider',
            'device_name' => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        $userId = null;
        $restaurantId = null;
        $riderId = null;

        $class = get_class($user);

        if ($class === Restaurant::class) {
            $restaurantId = $user->id;
        } elseif ($class === Rider::class) {
            $riderId = $user->id;
        } else {
            $userId = $user->id;
        }

        FcmToken::updateOrCreate(
            [
                'token' => $validated['token'],
            ],
            [
                'user_id' => $userId,
                'restaurant_id' => $restaurantId,
                'rider_id' => $riderId,
                'platform' => $validated['platform'],
                'app_type' => $validated['app_type'],
                'device_name' => $validated['device_name'] ?? null,
                'last_used_at' => now(),
            ]
        );

        return response()->json(['message' => 'Token registered']);
    }

    public function destroy(Request $request)
    {
        $request->validate(['token' => 'required|string']);

        FcmToken::where('token', $request->input('token'))->delete();

        return response()->json(['message' => 'Token removed']);
    }
}
