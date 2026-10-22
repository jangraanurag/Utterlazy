<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $favorites = Favorite::where('user_id', $request->user()->id)
            ->with('restaurant')
            ->get()
            ->pluck('restaurant');

        return response()->json(['favorites' => $favorites]);
    }

    public function toggle(Request $request)
    {
        $validated = $request->validate([
            'restaurant_id' => 'required|uuid',
        ]);

        $existing = Favorite::where('user_id', $request->user()->id)
            ->where('restaurant_id', $validated['restaurant_id'])
            ->first();

        if ($existing) {
            $existing->delete();

            return response()->json(['message' => 'Removed from favorites', 'is_favorite' => false]);
        }

        Favorite::create([
            'user_id' => $request->user()->id,
            'restaurant_id' => $validated['restaurant_id'],
        ]);

        return response()->json(['message' => 'Added to favorites', 'is_favorite' => true]);
    }
}
