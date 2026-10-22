<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function index(Request $request)
    {
        $prefs = NotificationPreference::firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'order_updates' => true,
                'offers' => true,
                'promotions' => true,
                'new_restaurants' => true,
                'rating_reminders' => true,
            ]
        );

        return response()->json(['preferences' => $prefs]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'order_updates' => 'sometimes|boolean',
            'offers' => 'sometimes|boolean',
            'promotions' => 'sometimes|boolean',
            'new_restaurants' => 'sometimes|boolean',
            'rating_reminders' => 'sometimes|boolean',
        ]);

        $prefs = NotificationPreference::firstOrCreate(
            ['user_id' => $request->user()->id]
        );

        $prefs->update($validated);

        return response()->json([
            'message' => 'Preferences updated',
            'preferences' => $prefs->fresh(),
        ]);
    }
}
