<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CouponController extends Controller
{
    /**
     * List all available coupons for the user, sorted by best discount.
     */
    public function available(Request $request)
    {
        $subtotal = (float) $request->query('subtotal', 0);
        $userId = $request->user()->id;

        $coupons = Coupon::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', now());
            })
            ->get()
            ->filter(fn ($c) => $c->isValidFor($userId, $subtotal))
            ->map(function ($c) use ($subtotal) {
                $discount = $subtotal > 0 ? $c->calculateDiscount($subtotal) : 0;

                return [
                    'id' => $c->id,
                    'code' => $c->code,
                    'title' => $c->title,
                    'description' => $c->description,
                    'type' => $c->type,
                    'value' => $c->value,
                    'min_order' => $c->min_order,
                    'max_discount' => $c->max_discount,
                    'discount_amount' => $discount,
                    'applicable' => $subtotal >= $c->min_order,
                ];
            })
            ->sortByDesc('discount_amount')
            ->values();

        return response()->json([
            'coupons' => $coupons,
            'best_coupon' => $coupons->first(),
        ]);
    }

    /**
     * Validate a specific coupon code.
     */
    public function validate(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'subtotal' => 'required|numeric|min:0',
        ]);

        $coupon = Coupon::where('code', strtoupper($request->code))->first();

        if (! $coupon) {
            return response()->json(['error' => 'Invalid coupon code.'], 404);
        }

        if (! $coupon->isValidFor($request->user()->id, $request->subtotal)) {
            return response()->json(['error' => 'This coupon is not available for your order.'], 422);
        }

        $discount = $coupon->calculateDiscount($request->subtotal);

        return response()->json([
            'coupon' => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'title' => $coupon->title,
                'type' => $coupon->type,
                'discount_amount' => $discount,
            ],
        ]);
    }

    /**
     * Fetch campaign feeds from Trackier Publisher API
     */
    private function fetchVCommissionCoupons()
    {
        $apiKey = env('VCOMMISSION_API_KEY') ?: '6a33a224c8f7416de6cc3e343dc6a33a224c8fae';
        if (! $apiKey) {
            return [];
        }

        try {
            $response = Http::timeout(5)
                ->get("https://api.trackier.com/v2/publisher/campaigns?apiKey={$apiKey}");

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['success']) && $data['success'] === true && isset($data['data']['campaigns'])) {
                    return $data['data']['campaigns'];
                }
            }
        } catch (\Exception $e) {
            Log::warning('vCommission Trackier fetch failed: '.$e->getMessage());
        }

        return [];
    }

    /**
     * Get a scratch card reward (vCommission/Trackier campaign or premium fallback deal)
     */
    public function getScratchCard(Request $request)
    {
        $userId = $request->user() ? $request->user()->id : 'guest';
        $cacheKey = "user_scratched_cards_{$userId}";

        // Retrieve recently shown card IDs/brands from cache
        $recentlyShown = Cache::get($cacheKey, []);
        if (! is_array($recentlyShown)) {
            $recentlyShown = [];
        }

        $campaigns = $this->fetchVCommissionCoupons();
        $selectedCampaign = null;

        if (! empty($campaigns)) {
            // Filter out recently shown campaign IDs
            $availableCampaigns = array_filter($campaigns, function ($camp) use ($recentlyShown) {
                return ! in_array($camp['id'] ?? null, $recentlyShown);
            });

            // Fallback to all campaigns if all of them have been shown recently
            if (empty($availableCampaigns)) {
                $availableCampaigns = $campaigns;
            }

            $availableCampaigns = array_values($availableCampaigns);
            shuffle($availableCampaigns);
            $item = $availableCampaigns[array_rand($availableCampaigns)];
            $selectedCampaignId = $item['id'] ?? null;

            $fullTitle = $item['title'] ?? '';
            $brand = 'vCommission Partner';
            $offerTitle = 'Exclusive Deal';

            // Parse title
            if (preg_match('/^(.*?)\s+by\s+(.*?)$/i', $fullTitle, $matches)) {
                $offerTitle = trim($matches[1]);
                $brandPart = trim($matches[2]);

                // Clean up suffixes from brand
                $brand = preg_replace('/(\s+(CPA|CPS|CPL|CPI|CPC))?(\s*-\s*India|\s+India|\s*-\s*Thailand|\s+Thailand|\s*-\s*Worldwide)?$/i', '', $brandPart);
                $brand = trim($brand);
            } else {
                // Look for Ecommerce, Travel, Insurance, Utility categories
                if (preg_match('/^(.*?)\s+(Ecommerce|Health Insurance|Insurance|Travel|Utility)\s+(CPA|CPS|CPL|CPI|CPC)?(.*)$/i', $fullTitle, $matches)) {
                    $brand = trim($matches[1]);
                    $offerTitle = trim($matches[2]).' Offer';
                } elseif (preg_match('/^(.*?)\s+(CPA|CPS|CPL|CPI|CPC)?\s*-\s*(India|Worldwide|Thailand)/i', $fullTitle, $matches)) {
                    $brand = trim($matches[1]);
                    $offerTitle = 'Exclusive Deal';
                } else {
                    $brand = preg_replace('/(\s+(CPA|CPS|CPL|CPI|CPC))?(\s*-\s*India|\s+India|\s*-\s*Thailand|\s+Thailand|\s*-\s*Worldwide)?$/i', '', $fullTitle);
                    $brand = trim($brand);
                    $offerTitle = 'Special Offer';
                }
            }

            // Strip common domain extensions from the brand name
            $brand = preg_replace('/\.(com|co\.in|in|org|net|co\.th)$/i', '', $brand);

            // Clean up lowercase brands to look better
            if (ctype_lower(str_replace(' ', '', $brand))) {
                $brand = ucwords($brand);
            }

            // Clean HTML description
            $rawDesc = $item['description'] ?? '';
            $desc = trim(strip_tags(html_entity_decode($rawDesc)));
            if (empty($desc)) {
                $desc = "Get amazing deals and discounts when shopping at {$brand}. Click Redeem to activate.";
            } else {
                // Normalize spaces and newlines
                $desc = preg_replace('/\s+/', ' ', $desc);
                if (strlen($desc) > 120) {
                    $desc = mb_substr($desc, 0, 117).'...';
                }
            }

            // Generate promo code based on brand name
            $cleanBrand = preg_replace('/[^A-Za-z0-9]/', '', $brand);
            $brandCode = strtoupper(substr($cleanBrand, 0, 5));
            if (empty($brandCode)) {
                $brandCode = 'VCOMM';
            }
            $code = $brandCode.'ACTIVE';

            // Store the campaign ID in cache history
            if ($selectedCampaignId) {
                $recentlyShown[] = $selectedCampaignId;
                if (count($recentlyShown) > 10) {
                    array_shift($recentlyShown);
                }
                Cache::put($cacheKey, $recentlyShown, now()->addDays(7));
            }

            $this->ensureCouponExists($code, $brand, $offerTitle, $desc);

            return response()->json([
                'success' => true,
                'source' => 'vcommission',
                'reward' => [
                    'brand' => $brand,
                    'title' => $offerTitle,
                    'description' => $desc,
                    'code' => $code,
                    'link' => $item['tracking_link'] ?? $item['link'] ?? '#',
                    'expiry' => 'Subject to change or termination by the merchant at any time.',
                ],
            ]);
        }

        // Fallback Curated Scratch Cards (Google Pay / Swiggy Style)
        $fallbacks = [
            [
                'brand' => "Domino's Pizza",
                'title' => 'Get 40% OFF',
                'description' => 'Get 40% off on your next Domino\'s order. Max discount ₹120.',
                'code' => 'DOMINOS40',
                'link' => 'https://www.dominos.co.in/',
                'expiry' => 'valid for 15 days',
            ],
            [
                'brand' => 'Myntra',
                'title' => 'Flat ₹150 OFF',
                'description' => 'Flat ₹150 off on apparel and accessories on orders above ₹999.',
                'code' => 'MYNTRA150',
                'link' => 'https://www.myntra.com/',
                'expiry' => 'valid for 30 days',
            ],
            [
                'brand' => 'Amazon Pay',
                'title' => 'Win up to ₹100 Cashback',
                'description' => 'Get up to ₹100 cashback on electricity or gas bill payments.',
                'code' => 'AMPAY100',
                'link' => 'https://www.amazon.in/',
                'expiry' => 'valid for 7 days',
            ],
            [
                'brand' => 'Boat Audio',
                'title' => 'Flat 15% OFF',
                'description' => 'Enjoy an extra 15% off on premium bluetooth headphones & speakers.',
                'code' => 'BOAT15',
                'link' => 'https://www.boat-lifestyle.com/',
                'expiry' => 'valid for 10 days',
            ],
            [
                'brand' => 'Swiggy Instamart',
                'title' => 'Flat ₹100 OFF',
                'description' => 'Save ₹100 on groceries with your first order of ₹499 or more.',
                'code' => 'INSTAMART100',
                'link' => 'https://www.swiggy.com/',
                'expiry' => 'valid for 30 days',
            ],
            [
                'brand' => 'Ajio',
                'title' => 'Flat 30% OFF',
                'description' => 'Get 30% off on premium fashion brands on orders above ₹1499.',
                'code' => 'AJIO30',
                'link' => 'https://www.ajio.com/',
                'expiry' => 'valid for 30 days',
            ],
            [
                'brand' => 'Mamaearth',
                'title' => 'Flat 20% OFF',
                'description' => 'Flat 20% off on all toxin-free skin and hair care products.',
                'code' => 'MAMA20',
                'link' => 'https://mamaearth.in/',
                'expiry' => 'valid for 15 days',
            ],
            [
                'brand' => 'Lenskart',
                'title' => 'Flat 50% OFF Gold Membership',
                'description' => 'Get 50% off on Lenskart Gold and buy-1-get-1 offers on eyeglasses.',
                'code' => 'LKGOLD50',
                'link' => 'https://www.lenskart.com/',
                'expiry' => 'valid for 30 days',
            ],
        ];

        // Filter out recently shown fallbacks by brand name
        $availableFallbacks = array_filter($fallbacks, function ($fb) use ($recentlyShown) {
            return ! in_array($fb['brand'] ?? null, $recentlyShown);
        });

        if (empty($availableFallbacks)) {
            $availableFallbacks = $fallbacks;
        }

        $availableFallbacks = array_values($availableFallbacks);
        shuffle($availableFallbacks);
        $reward = $availableFallbacks[array_rand($availableFallbacks)];

        // Store fallback brand name in cache history
        $recentlyShown[] = $reward['brand'];
        if (count($recentlyShown) > 10) {
            array_shift($recentlyShown);
        }
        Cache::put($cacheKey, $recentlyShown, now()->addDays(7));

        $this->ensureCouponExists($reward['code'], $reward['brand'], $reward['title'], $reward['description']);

        return response()->json([
            'success' => true,
            'source' => 'fallback',
            'reward' => $reward,
        ]);
    }

    private function ensureCouponExists(string $code, string $brand, string $title, string $description)
    {
        $coupon = Coupon::where('code', $code)->first();
        if (! $coupon) {
            $type = 'flat';
            $value = 100.00;
            $minOrder = 299.00;
            $maxDiscount = null;

            if (preg_match('/(\d+)%/', $title, $matches)) {
                $type = 'percentage';
                $value = (float) $matches[1];
                $maxDiscount = 150.00;
            } elseif (preg_match('/₹(\d+)/', $title, $matches)) {
                $type = 'flat';
                $value = (float) $matches[1];
                $minOrder = $value * 2;
            }

            Coupon::create([
                'code' => $code,
                'title' => "{$brand} - {$title}",
                'description' => $description,
                'type' => $type,
                'value' => $value,
                'min_order' => $minOrder,
                'max_discount' => $maxDiscount,
                'usage_limit' => null,
                'per_user_limit' => 1,
                'times_used' => 0,
                'is_active' => true,
                'valid_from' => now()->subDay(),
                'valid_until' => now()->addDays(30),
            ]);
        }
    }
}
