<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VoiceSearchService;
use Illuminate\Http\Request;

class VoiceSearchController extends Controller
{
    protected VoiceSearchService $voiceSearch;

    public function __construct(VoiceSearchService $voiceSearch)
    {
        $this->voiceSearch = $voiceSearch;
    }

    public function search(Request $request)
    {
        $validated = $request->validate([
            'text' => 'required|string|min:1|max:500',
            'lat' => 'sometimes|numeric',
            'lng' => 'sometimes|numeric',
        ]);

        $intent = $this->voiceSearch->parseIntent($validated['text']);
        $results = $this->voiceSearch->searchByIntent($intent, $validated['lat'] ?? null, $validated['lng'] ?? null);

        return response()->json($results);
    }
}
