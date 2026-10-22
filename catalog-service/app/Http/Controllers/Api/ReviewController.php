<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Review;
use App\Services\FcmService;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Get reviews for a restaurant
     */
    public function index(Request $request, $restaurantId)
    {
        $reviews = Review::where('restaurant_id', $restaurantId)
            ->with('user:id,name')
            ->latest()
            ->paginate(20);

        // Calculate rating breakdown
        $ratingBreakdown = Review::where('restaurant_id', $restaurantId)
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->get()
            ->pluck('count', 'rating')
            ->toArray();

        $avgRating = Review::where('restaurant_id', $restaurantId)->avg('rating');
        $totalReviews = Review::where('restaurant_id', $restaurantId)->count();

        return response()->json([
            'reviews' => $reviews,
            'summary' => [
                'average_rating' => round($avgRating, 1),
                'total_reviews' => $totalReviews,
                'breakdown' => $ratingBreakdown,
            ],
        ]);
    }

    /**
     * Create a review with optional photos
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'restaurant_id' => 'required|uuid',
            'order_id' => 'sometimes|uuid|nullable',
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'sometimes|string|max:100',
            'comment' => 'required|string|max:1000',
            'food_rating' => 'sometimes|integer|min:1|max:5',
            'delivery_rating' => 'sometimes|integer|min:1|max:5',
            'packaging_rating' => 'sometimes|integer|min:1|max:5',
            'photos' => 'sometimes|array|max:5',
            'photos.*' => 'file|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        // Handle photo uploads
        $photoUrls = [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $photoUrls[] = ImageUploadService::upload($photo, 'review-photos');
            }
        }

        $review = Review::create([
            'user_id' => $request->user()->id,
            'restaurant_id' => $validated['restaurant_id'],
            'order_id' => $validated['order_id'] ?? null,
            'rating' => $validated['rating'],
            'title' => $validated['title'] ?? null,
            'comment' => $validated['comment'],
            'food_rating' => $validated['food_rating'] ?? null,
            'delivery_rating' => $validated['delivery_rating'] ?? null,
            'packaging_rating' => $validated['packaging_rating'] ?? null,
            'photos' => $photoUrls,
        ]);

        // Update restaurant average rating
        $avgRating = Review::where('restaurant_id', $validated['restaurant_id'])->avg('rating');
        Restaurant::where('id', $validated['restaurant_id'])->update(['rating' => round($avgRating, 1)]);

        // Notify vendor of new review via FCM
        $restaurant = Restaurant::find($validated['restaurant_id']);
        if ($restaurant) {
            $fcm = app(FcmService::class);
            $stars = str_repeat('⭐', $validated['rating']);
            $fcm->sendToRestaurant(
                $restaurant,
                [
                    'type' => 'new_review',
                    'review_id' => (string) $review->id,
                    'rating' => (string) $validated['rating'],
                    'screen' => 'reviews',
                ],
                'New Review Received',
                "{$stars} {$validated['rating']}/5 — {$validated['comment']}"
            );
        }

        return response()->json([
            'message' => 'Review submitted',
            'review' => $review->load('user:id,name'),
        ], 201);
    }

    /**
     * Get user's own reviews
     */
    public function myReviews(Request $request)
    {
        $reviews = Review::where('user_id', $request->user()->id)
            ->with('restaurant:id,name,logo_url')
            ->latest()
            ->paginate(20);

        return response()->json($reviews);
    }

    /**
     * Mark a review as helpful
     */
    public function helpful(Review $review)
    {
        $review->increment('helpful_count');

        return response()->json(['message' => 'Marked as helpful', 'count' => $review->fresh()->helpful_count]);
    }

    /**
     * Report a review
     */
    public function report(Request $request, Review $review)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $review->update(['is_reported' => true, 'report_reason' => $validated['reason']]);

        return response()->json(['message' => 'Review reported. We will review it shortly.']);
    }

    /**
     * Vendor replies to a review
     */
    public function reply(Request $request, Review $review)
    {
        $restaurantId = $request->user()->id;
        if ($review->restaurant_id !== $restaurantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'vendor_reply' => 'required|string|max:1000',
        ]);

        $data = [
            'vendor_reply' => $validated['vendor_reply'],
            'vendor_replied_at' => now(),
        ];

        // If refund details are provided, process them too
        if ($request->has('refund_amount') || $request->refund_type === 'full') {
            $orderTotal = $review->order?->grand_total ?? 0;

            if ($request->refund_type === 'full') {
                $refundAmount = $orderTotal;
            } else {
                $refundAmount = (float) $request->refund_amount;
            }

            if ($refundAmount <= 0) {
                return response()->json(['error' => 'Refund amount must be greater than 0'], 422);
            }

            if ($refundAmount > $orderTotal && $orderTotal > 0) {
                return response()->json(['error' => 'Refund cannot exceed order total of '.$orderTotal], 422);
            }

            $data['refund_amount'] = $refundAmount;
            $data['refund_status'] = 'processed';
            $data['refunded_at'] = now();

            // Update order with refund info
            if ($review->order) {
                $review->order->update([
                    'refund_amount' => ($review->order->refund_amount ?? 0) + $refundAmount,
                    'refund_status' => 'refunded',
                ]);
            }
        }

        $review->update($data);

        // Notify customer
        $fcm = app(FcmService::class);
        $customer = $review->user;
        if ($customer) {
            $isRefund = $request->has('refund_amount') || $request->refund_type === 'full';
            $fcm->sendToUser(
                $customer,
                'customer',
                [
                    'type' => $isRefund ? 'review_refund' : 'review_reply',
                    'review_id' => (string) $review->id,
                    'order_id' => (string) $review->order_id,
                    'screen' => 'review_detail',
                ],
                $isRefund ? 'Refund Processed & Reply Posted' : 'Vendor Replied to Your Review',
                $isRefund
                    ? "Vendor replied and issued ₹{$refundAmount} refund"
                    : 'Vendor replied to your review: "'.substr($validated['vendor_reply'], 0, 50).'..."'
            );
        }

        return response()->json([
            'message' => $request->has('refund_amount') || $request->refund_type === 'full' ? 'Reply posted and refund processed' : 'Reply posted',
            'review' => $review->fresh(),
        ]);
    }
}
