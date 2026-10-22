<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|max:5120|mimes:jpg,jpeg,png,webp,gif',
            'type' => 'required|in:restaurant_logo,menu_item,rider_photo,user_avatar',
        ]);

        $file = $request->file('file');

        $url = match ($validated['type']) {
            'restaurant_logo' => ImageUploadService::uploadRestaurantLogo($file),
            'menu_item' => ImageUploadService::uploadMenuItemImage($file),
            'rider_photo' => ImageUploadService::uploadRiderPhoto($file),
            'user_avatar' => ImageUploadService::uploadUserAvatar($file),
        };

        return response()->json([
            'message' => 'File uploaded successfully',
            'url' => $url,
        ]);
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'url' => 'required|url',
        ]);

        $deleted = ImageUploadService::delete($validated['url']);

        return response()->json([
            'message' => $deleted ? 'File deleted' : 'File not found',
        ]);
    }
}
