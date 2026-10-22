<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartSuggestion;
use App\Models\CateringRequest;
use App\Models\DarkKitchenBrand;
use App\Models\LeaderboardEntry;
use App\Models\MealDonation;
use App\Models\Referral;
use App\Models\SocialLike;
use App\Models\SocialPost;
use Illuminate\Http\Request;

class P2FeaturesController extends Controller
{
    // ─── Catering ───
    public function cateringRequest(Request $request)
    {
        $v = $request->validate([
            'restaurant_id' => 'required|exists:restaurants,id',
            'event_type' => 'required|in:party,corporate,wedding,other',
            'event_date' => 'required|date|after:today',
            'guest_count' => 'required|integer|min:5',
            'budget_per_person' => 'sometimes|numeric|min:50',
            'menu_preferences' => 'sometimes|string',
        ]);

        $catering = CateringRequest::create(array_merge($v, ['user_id' => $request->user()->id]));

        return response()->json(['message' => 'Catering request sent!', 'catering' => $catering], 201);
    }

    // ─── Donate a Meal ───
    public function donate(Request $request)
    {
        $v = $request->validate([
            'amount' => 'required|numeric|min:10|max:10000',
            'ngo_name' => 'sometimes|string',
        ]);

        $donation = MealDonation::create([
            'user_id' => $request->user()->id,
            'amount' => $v['amount'],
            'ngo_name' => $v['ngo_name'] ?? null,
        ]);

        return response()->json(['message' => "Thank you! ₹{$v['amount']} donated.", 'donation' => $donation], 201);
    }

    // ─── Dark Kitchen Brands ───
    public function brandList(Request $request)
    {
        $restaurant = $request->user();

        return response()->json(['brands' => DarkKitchenBrand::where('restaurant_id', $restaurant->id)->where('is_active', true)->get()]);
    }

    public function createBrand(Request $request)
    {
        $restaurant = $request->user();
        $v = $request->validate([
            'brand_name' => 'required|string|max:255',
            'cuisine_type' => 'sometimes|string',
            'description' => 'sometimes|string',
        ]);

        $brand = DarkKitchenBrand::create(array_merge($v, ['restaurant_id' => $restaurant->id]));

        return response()->json(['message' => 'Brand created!', 'brand' => $brand], 201);
    }

    // ─── Social Feed ───
    public function createPost(Request $request)
    {
        $v = $request->validate([
            'type' => 'required|in:food_photo,review,checkin',
            'caption' => 'sometimes|string|max:500',
            'image_url' => 'sometimes|string',
            'tags' => 'sometimes|array',
            'order_id' => 'sometimes|exists:orders,id',
        ]);

        $post = SocialPost::create(array_merge($v, ['user_id' => $request->user()->id]));

        return response()->json(['message' => 'Post created!', 'post' => $post], 201);
    }

    public function feed(Request $request)
    {
        $posts = SocialPost::with('user:id,name')->orderBy('created_at', 'desc')->paginate(20);

        return response()->json(['posts' => $posts]);
    }

    public function toggleLike(Request $request, SocialPost $post)
    {
        $existing = SocialLike::where('post_id', $post->id)->where('user_id', $request->user()->id)->first();
        if ($existing) {
            $existing->delete();
            $post->decrement('likes_count');

            return response()->json(['liked' => false, 'likes_count' => $post->fresh()->likes_count]);
        }

        SocialLike::create(['post_id' => $post->id, 'user_id' => $request->user()->id]);
        $post->increment('likes_count');

        return response()->json(['liked' => true, 'likes_count' => $post->fresh()->likes_count]);
    }

    // ─── Leaderboards ───
    public function leaderboard(Request $request)
    {
        $period = $request->period ?? 'weekly';
        $category = $request->category ?? 'orders';

        $entries = LeaderboardEntry::where('period', $period)->where('category', $category)
            ->with('user:id,name')->orderBy('rank')->limit(20)->get();

        $myEntry = LeaderboardEntry::where('user_id', $request->user()->id)
            ->where('period', $period)->where('category', $category)->first();

        return response()->json(['leaderboard' => $entries, 'my_rank' => $myEntry?->rank, 'my_score' => $myEntry?->score]);
    }

    // ─── Referral v2 ───
    public function referralStats(Request $request)
    {
        $referrals = Referral::where('referrer_user_id', $request->user()->id)->with('referredUser:id,name')->get();

        return response()->json([
            'total_referrals' => $referrals->count(),
            'successful' => $referrals->where('status', 'completed')->count(),
            'total_earned' => $referrals->where('status', 'completed')->sum('reward_amount'),
            'referrals' => $referrals,
            'referral_code' => $request->user()->referral_code ?? strtoupper(substr($request->user()->name, 0, 4).rand(1000, 9999)),
        ]);
    }

    // ─── Smart Cart Suggestions ───
    public function cartSuggestions(Request $request)
    {
        $validated = $request->validate([
            'item_ids' => 'required|array',
            'item_ids.*' => 'exists:menu_items,id',
        ]);

        $suggestions = CartSuggestion::whereIn('menu_item_id', $validated['item_ids'])
            ->where('score', '>=', 5)
            ->with('suggestedItem:id,name,price,image_url')
            ->orderBy('score', 'desc')
            ->limit(3)
            ->get()
            ->map(fn ($s) => $s->suggestedItem);

        return response()->json(['suggestions' => $suggestions]);
    }
}
