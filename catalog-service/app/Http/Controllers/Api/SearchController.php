<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return response()->json([
                'restaurants' => [],
                'dishes' => [],
            ]);
        }

        $restaurants = Restaurant::where('name', 'LIKE', "%{$query}%")
            ->orWhere('description', 'LIKE', "%{$query}%")
            ->limit(10)
            ->get();

        $dishes = MenuItem::with('restaurant:id,name')
            ->where('name', 'LIKE', "%{$query}%")
            ->orWhere('description', 'LIKE', "%{$query}%")
            ->limit(20)
            ->get();

        return response()->json([
            'restaurants' => $restaurants,
            'dishes' => $dishes,
        ]);
    }
}
