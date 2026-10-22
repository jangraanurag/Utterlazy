<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\AdAuctionService;
use App\Services\AdRecommendationService;
use Illuminate\Http\Request;

class AdController extends Controller
{
    public function __construct(
        private AdAuctionService $auctionService,
        private AdRecommendationService $adService
    ) {}

    /**
     * Get sponsored ad results for a placement.
     * GET /sponsored-results?placement=listings|search|featured&restaurant_id=&search=
     */
    public function sponsoredResults(Request $request)
    {
        $request->validate([
            'placement'     => 'required|in:listings,search,featured',
            'restaurant_id' => 'nullable|string',
            'search'        => 'nullable|string|max:100',
        ]);

        $user = $request->user();
        $isNewUser = $user && Order::where('user_id', $user->id)->count() === 0;

        $results = $this->auctionService->getSponsoredResults(
            $request->placement,
            3,
            $user?->id,
            $request->restaurant_id,
            $request->search,
            $isNewUser,
        );

        return response()->json(['sponsored' => $results]);
    }

    /**
     * Log an impression event for a sponsored listing.
     * POST /sponsored-impression
     */
    public function logImpression(Request $request)
    {
        $request->validate([
            'listing_id' => 'required|string|exists:sponsored_listings,id',
        ]);

        $this->adService->logImpression(
            $request->listing_id,
            (string) ($request->user()?->id ?? 'guest'),
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Log a click event (with fraud dedup).
     * POST /sponsored-click
     */
    public function logClick(Request $request)
    {
        $request->validate([
            'listing_id' => 'required|string|exists:sponsored_listings,id',
            'session_id' => 'required|string|max:64',
        ]);

        $valid = $this->adService->logClick(
            $request->listing_id,
            (string) ($request->user()?->id ?? 'guest'),
            $request->session_id,
        );

        return response()->json(['ok' => true, 'charged' => $valid]);
    }
}
