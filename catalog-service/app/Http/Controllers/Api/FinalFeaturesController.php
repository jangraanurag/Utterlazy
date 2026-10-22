<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\FoodGallery;
use App\Models\Recipe;
use Illuminate\Http\Request;

class FinalFeaturesController extends Controller
{
    // ─── Food Image Gallery ───
    public function uploadPhoto(Request $request)
    {
        $v = $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'menu_item_id' => 'sometimes|exists:menu_items,id',
            'order_id' => 'sometimes|exists:orders,id',
            'caption' => 'sometimes|string|max:200',
        ]);

        $path = $request->file('image')->store("food-gallery/{$request->user()->id}", 'public');

        $photo = FoodGallery::create([
            'user_id' => $request->user()->id,
            'menu_item_id' => $v['menu_item_id'] ?? null,
            'order_id' => $v['order_id'] ?? null,
            'image_url' => $path,
            'caption' => $v['caption'] ?? null,
        ]);

        return response()->json(['message' => 'Photo uploaded!', 'photo' => $photo], 201);
    }

    public function gallery()
    {
        $photos = FoodGallery::with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->paginate(30);

        return response()->json(['gallery' => $photos]);
    }

    // ─── Blog / Magazine ───
    public function blogPosts()
    {
        $posts = BlogPost::where('status', 'published')
            ->orderBy('published_at', 'desc')
            ->paginate(10);

        return response()->json(['posts' => $posts]);
    }

    public function blogPost($slug)
    {
        $post = BlogPost::where('slug', $slug)->where('status', 'published')->firstOrFail();

        return response()->json(['post' => $post]);
    }

    // ─── Recipes ───
    public function recipes(Request $request)
    {
        $query = Recipe::query();
        if ($request->cuisine) {
            $query->where('cuisine', $request->cuisine);
        }
        if ($request->difficulty) {
            $query->where('difficulty', $request->difficulty);
        }

        return response()->json(['recipes' => $query->orderBy('title')->paginate(20)]);
    }

    public function recipe($id)
    {
        return response()->json(['recipe' => Recipe::findOrFail($id)]);
    }
}
