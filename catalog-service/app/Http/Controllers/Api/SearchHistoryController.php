<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserSearch;
use Illuminate\Http\Request;

class SearchHistoryController extends Controller
{
    public function recent(Request $request)
    {
        $searches = UserSearch::where('user_id', $request->user()->id)
            ->latest()
            ->limit(10)
            ->pluck('query')
            ->unique()
            ->values();

        return response()->json(['recent_searches' => $searches]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'query' => 'required|string|max:100',
        ]);

        UserSearch::create([
            'user_id' => $request->user()->id,
            'query' => $validated['query'],
        ]);

        return response()->json(['message' => 'Search saved']);
    }

    public function clear(Request $request)
    {
        UserSearch::where('user_id', $request->user()->id)->delete();

        return response()->json(['message' => 'Search history cleared']);
    }
}
