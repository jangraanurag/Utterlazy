<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\MenuItem;
use App\Services\POSIntegrationService;
use Illuminate\Http\Request;

class POSController extends Controller
{
    protected POSIntegrationService $posService;

    public function __construct(POSIntegrationService $posService)
    {
        $this->posService = $posService;
    }

    public function configure(Request $request)
    {
        $restaurant = $request->user();

        $validated = $request->validate([
            'platform' => 'required|in:petpooja,posist,limetray,inresto,dotpe',
            'api_key' => 'required|string',
            'endpoint' => 'required|url',
            'menu_endpoint' => 'sometimes|url',
        ]);

        $restaurant->update([
            'pos_integration' => json_encode($validated),
            'pos_sync_status' => 'configured',
        ]);

        return response()->json([
            'message' => "Connected to {$validated['platform']}! Orders will now sync automatically.",
            'pos_integration' => $validated['platform'],
        ]);
    }

    public function status(Request $request)
    {
        $restaurant = $request->user();
        $config = $restaurant->pos_integration ? json_decode($restaurant->pos_integration, true) : null;

        return response()->json([
            'configured' => $config !== null,
            'platform' => $config['platform'] ?? null,
            'sync_status' => $restaurant->pos_sync_status,
        ]);
    }

    public function disconnect(Request $request)
    {
        $restaurant = $request->user();
        $restaurant->update(['pos_integration' => null, 'pos_sync_status' => 'not_configured']);

        return response()->json(['message' => 'POS disconnected.']);
    }

    public function pullMenu(Request $request)
    {
        $restaurant = $request->user();

        if (! $restaurant->pos_integration) {
            return response()->json(['error' => 'No POS configured.'], 422);
        }

        $items = $this->posService->pullMenu($restaurant);

        if (empty($items)) {
            return response()->json(['error' => 'Failed to pull menu from POS.'], 500);
        }

        $created = ['items' => 0, 'categories' => []];
        foreach ($items as $itemData) {
            $category = Category::firstOrCreate([
                'restaurant_id' => $restaurant->id,
                'name' => $itemData['category'] ?? 'Imported',
            ]);
            if (! in_array($category->id, $created['categories'])) {
                $created['categories'][] = $category->id;
            }

            MenuItem::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'name' => $itemData['name']],
                [
                    'category_id' => $category->id,
                    'price' => $itemData['price'],
                    'description' => $itemData['description'] ?? null,
                    'is_available' => $itemData['is_available'] ?? true,
                    'is_veg' => $itemData['is_veg'] ?? false,
                    'hsn_code' => $itemData['hsn_code'] ?? null,
                    'gst_percent' => $itemData['gst_percent'] ?? 0,
                ]
            );
            $created['items']++;
        }

        return response()->json([
            'message' => "Imported {$created['items']} items across ".count($created['categories']).' categories from your POS.',
            'created' => $created,
        ]);
    }
}
