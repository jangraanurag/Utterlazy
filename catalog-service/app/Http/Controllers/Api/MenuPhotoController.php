<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MenuPhotoService;
use Illuminate\Http\Request;

class MenuPhotoController extends Controller
{
    protected MenuPhotoService $menuPhotoService;

    public function __construct(MenuPhotoService $menuPhotoService)
    {
        $this->menuPhotoService = $menuPhotoService;
    }

    public function analyze(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png|max:10240',
        ]);

        $path = $request->file('photo')->store('menu-photos', 'public');

        $data = $this->menuPhotoService->analyzeMenuPhoto(storage_path("app/public/{$path}"));

        return response()->json([
            'message' => 'Menu analyzed successfully',
            'photo_url' => asset("storage/{$path}"),
            'extracted' => $data,
        ]);
    }

    public function confirmSave(Request $request)
    {
        $validated = $request->validate([
            'restaurant_id' => 'required|exists:restaurants,id',
            'extracted' => 'required|array',
            'extracted.categories' => 'required|array',
        ]);

        $restaurant = $request->user();
        if ($restaurant->id !== $validated['restaurant_id']) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $result = $this->menuPhotoService->createMenuFromExtraction(
            $validated['restaurant_id'],
            $validated['extracted']
        );

        return response()->json([
            'message' => "Created {$result['categories']} categories and {$result['items']} menu items.",
            'created' => $result,
        ]);
    }
}
