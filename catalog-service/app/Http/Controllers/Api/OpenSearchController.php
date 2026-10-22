<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Search\OpenSearchClient;
use Illuminate\Http\Request;

class OpenSearchController extends Controller
{
    public function search(Request $request)
    {
        $validated = $request->validate([
            'q' => 'sometimes|string|max:80',
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);

        $client = OpenSearchClient::fromConfig();

        if (! $client) {
            return app(SearchController::class)->search($request);
        }

        $query = [
            'size' => $validated['limit'] ?? 20,
            'query' => [
                'bool' => [
                    'filter' => [['term' => ['is_active' => true]]],
                    'must' => empty($validated['q'])
                        ? [['match_all' => (object) []]]
                        : [[
                            'multi_match' => [
                                'query' => $validated['q'],
                                'fields' => ['name^3', 'cuisine_type'],
                                'fuzziness' => 'AUTO',
                            ],
                        ]],
                ],
            ],
        ];

        $result = $client->search($query);

        if ($result === null) {
            // Search must never hard-fail: fall back to MySQL.
            return app(SearchController::class)->search($request);
        }

        $hits = collect($result['hits']['hits'] ?? [])
            ->map(fn ($hit) => $hit['_source'] ?? [])
            ->values();

        return response()->json([
            'source' => 'opensearch',
            'total' => $result['hits']['total']['value'] ?? $hits->count(),
            'results' => $hits,
        ]);
    }
}
