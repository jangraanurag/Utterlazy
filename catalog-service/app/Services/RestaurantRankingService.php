<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\SponsoredListing;
use Illuminate\Support\Facades\DB;

class RestaurantRankingService
{
    const FRESHNESS_DAYS = 14;

    const DIVERSITY_PENALTY = 0.85;

    const RATING_GOLD_THRESHOLD = 4.2;

    const RATING_BAD_THRESHOLD = 4.0;

    const CANCELLATION_SIGMOID_THETA = 0.03;

    const CANCELLATION_SIGMOID_K = 50;

    const EMA_ALPHA_HIGH_VOLUME = 0.1;

    const EMA_ALPHA_NEW = 0.5;

    const EMA_ALPHA_DEFAULT = 0.25;

    const HIGH_VOLUME_THRESHOLD = 1000;

    const NEW_RESTAURANT_THRESHOLD = 100;

    const SPONSORED_SLOTS = [2, 5, 9];

    // ──────────────────────────────────────────────
    //  Dynamic weights based on peak/off-peak
    // ──────────────────────────────────────────────

    private static function weights(int $hour): array
    {
        $peak = ($hour >= 12 && $hour <= 14) || ($hour >= 19 && $hour <= 22);

        return $peak ? [
            'organic' => 0.30,
            'operational' => 0.25,
            'rider' => 0.12,
            'proximity' => 0.18,
            'exploration' => 0.03,
            'sponsored' => 0.12,
        ] : [
            'organic' => 0.35,
            'operational' => 0.15,
            'rider' => 0.08,
            'proximity' => 0.20,
            'exploration' => 0.12,
            'sponsored' => 0.10,
        ];
    }

    // ──────────────────────────────────────────────
    //  Entry point
    // ──────────────────────────────────────────────

    public static function getRankedRestaurants(
        ?float $userLat = null,
        ?float $userLng = null,
        ?int $userId = null,
        ?string $category = null,
        int $limit = 50,
        bool $gstReady = false
    ): array {
        $restaurants = self::getCandidateRestaurants($userLat, $userLng, $category, $gstReady);
        if ($restaurants->isEmpty()) {
            return [];
        }

        $userPreferences = self::getUserCategoryPreferences($userId);
        $hour = (int) now()->format('H');
        $w = self::weights($hour);
        $totalOrders = max(1, Order::count());
        $dow = (int) now()->format('N');
        $isWeekend = $dow >= 6;

        $scored = $restaurants->map(function ($r) use ($userLat, $userLng, $userId, $userPreferences, $hour, $w, $isWeekend) {
            $proximityScore = self::scoreProximity($r, $userLat, $userLng, $hour);
            $organicScore = self::scoreOrganic($r, $hour, $isWeekend);
            $operationalScore = self::scoreOperational($r);
            $riderFeedbackScore = self::scoreRiderFeedback($r);
            $explorationScore = self::scoreThompsonSampling($r);
            $sponsoredScore = self::scoreSponsoredGSP($r, $userLat, $userLng, $userId);
            $batchingScore = self::scoreBatchingCompatibility($r);
            $personalizationMultiplier = self::scorePersonalization($r, $userPreferences, $hour, $isWeekend);

            $finalScore = ($w['organic'] * $organicScore)
                        + ($w['operational'] * $operationalScore)
                        + ($w['rider'] * $riderFeedbackScore)
                        + ($w['proximity'] * $proximityScore)
                        + ($w['exploration'] * $explorationScore)
                        + ($w['sponsored'] * $sponsoredScore);

            $finalScore *= $personalizationMultiplier;

            $r->_ranking_score = round($finalScore, 4);
            $r->_sub_scores = [
                'organic' => round($organicScore, 2),
                'operational' => round($operationalScore, 2),
                'rider' => round($riderFeedbackScore, 2),
                'proximity' => round($proximityScore, 2),
                'exploration' => round($explorationScore, 2),
                'sponsored' => round($sponsoredScore, 2),
                'batching' => round($batchingScore, 2),
                'personalization' => round($personalizationMultiplier, 2),
            ];

            return $r;
        })->filter(fn ($r) => $r->_ranking_score > 0)
            ->sortByDesc('_ranking_score');

        $ranked = $scored->values()->all();

        $ranked = self::injectSponsoredPodium($ranked, $scored, $userLat, $userLng, $userId);

        $ranked = self::injectDiversity($ranked);

        return array_slice($ranked, 0, $limit);
    }

    // ──────────────────────────────────────────────
    //  Candidate Generation  (§1.1)
    // ──────────────────────────────────────────────

    private static function getCandidateRestaurants(?float $lat, ?float $lng, ?string $category, bool $gstReady = false)
    {
        $radius = 15;

        $query = Restaurant::where('is_active', true)
            ->where('is_accepting_orders', true)
            ->whereNotNull('latitude')->whereNotNull('longitude');

        if ($gstReady) {
            $query->whereNotNull('gst_number')->where('gst_number', '!=', '');
        }

        if ($lat && $lng) {
            $query->selectRaw('
                *,
                (6371 * acos(
                    cos(radians(?)) * cos(radians(latitude))
                    * cos(radians(longitude) - radians(?))
                    + sin(radians(?)) * sin(radians(latitude))
                )) AS distance_km
            ', [$lat, $lng, $lat])
                ->having('distance_km', '<=', $radius);
        }

        if ($category) {
            $query->whereHas('menuItems', function ($mq) use ($category) {
                $mq->where('is_available', true)
                    ->whereHas('globalCategory', function ($gq) use ($category) {
                        $gq->where('id', $category)->orWhere('name', $category);
                    });
            });
        }

        return $query->get();
    }

    // ──────────────────────────────────────────────
    //  Proximity Score  (§1.1)
    // ──────────────────────────────────────────────

    private static function scoreProximity(Restaurant $r, ?float $userLat, ?float $userLng, int $hour): float
    {
        if (! $userLat || ! $userLng) {
            return 50;
        }
        $dist = $r->distance_km ?? RiderAssignmentService::haversineDistance(
            $r->latitude, $r->longitude, $userLat, $userLng
        );

        $base = max(0, 100 - ($dist * 8));

        if (($hour >= 12 && $hour <= 14) || ($hour >= 19 && $hour <= 22)) {
            $base = max(0, $base - 5);
        }

        return $base;
    }

    // ──────────────────────────────────────────────
    //  Organic Score  (§3.1, §8.1)
    //  Uses EMA consumer rating, threshold effects
    // ──────────────────────────────────────────────

    private static function scoreOrganic(Restaurant $r, int $hour, bool $isWeekend): float
    {
        $emaRating = $r->ema_consumer_rating ?? $r->rating ?? 4.0;

        // ── Threshold Effect (§8.1) ──
        // Rating ≥ 4.2 gets 25% weight boost; < 4.0 gets severe penalty
        if ($emaRating >= self::RATING_GOLD_THRESHOLD) {
            $ratingScore = ($emaRating * 20) * 1.25;
        } elseif ($emaRating < self::RATING_BAD_THRESHOLD) {
            $ratingScore = ($emaRating * 20) * 0.5;
        } else {
            $ratingScore = $emaRating * 20;
        }

        // Volume (temporal decay: last 30 days weighted more)
        $recentOrders = Order::where('restaurant_id', $r->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        $volumeScore = min(100, $recentOrders * 2);

        // Conversion rate
        $completedOrders = Order::where('restaurant_id', $r->id)
            ->whereIn('status', ['delivered', 'completed'])->count();
        $totalOrders = max(1, Order::where('restaurant_id', $r->id)->count());
        $conversionRate = ($completedOrders / $totalOrders) * 100;
        $conversionScore = min(100, $conversionRate);

        // Time-of-day relevance
        $hourScore = self::timeOfDayMatch($r, $hour);

        // Weekend boost for experiential dining (§2.2)
        $weekendMultiplier = $isWeekend ? 1.1 : 1.0;

        return ((0.40 * $ratingScore) + (0.25 * $volumeScore) + (0.20 * $conversionScore) + (0.15 * $hourScore)) * $weekendMultiplier;
    }

    // ──────────────────────────────────────────────
    //  Time-of-Day Match
    // ──────────────────────────────────────────────

    private static function timeOfDayMatch(Restaurant $r, int $hour): float
    {
        if (! $r->operating_hours) {
            return 50;
        }

        $dayName = strtolower(now()->format('l'));
        $hours = $r->operating_hours;
        if (isset($hours[$dayName]['open'], $hours[$dayName]['close'])) {
            $open = (int) explode(':', $hours[$dayName]['open'])[0];
            $close = (int) explode(':', $hours[$dayName]['close'])[0];
            if ($hour >= $open && $hour < $close) {
                return 100;
            }
        }

        return 30;
    }

    // ──────────────────────────────────────────────
    //  Operational Score  (§6.2, §6.3)
    //  Sigmoid cancellation penalty, asymmetric loss
    // ──────────────────────────────────────────────

    private static function scoreOperational(Restaurant $r): float
    {
        $recentOrders = Order::where('restaurant_id', $r->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->get();

        if ($recentOrders->isEmpty()) {
            return 75;
        }

        $total = max(1, $recentOrders->count());

        // ── Cancellation Rate with Sigmoid Penalty (§6.2) ──
        $cancelled = $recentOrders->whereIn('status', ['cancelled', 'rejected'])->count();
        $cancelRate = $cancelled / $total;

        $cancelPenalty = self::sigmoidPenalty($cancelRate, self::CANCELLATION_SIGMOID_THETA, self::CANCELLATION_SIGMOID_K);
        $cancellationScore = 100 * (1 - $cancelPenalty);

        // ── Asymmetric Prep Time Loss (§6.3) ──
        $delayed = $recentOrders->filter(fn ($o) => $o->prep_delay_minutes > 5)->count();
        $lateRate = $delayed / $total;

        $earlyOrders = $recentOrders->filter(fn ($o) => $o->prep_delay_minutes !== null && $o->prep_delay_minutes < -5)->count();
        $earlyRate = $earlyOrders / $total;

        $asymmetricPenalty = ($lateRate * 3.0 * 50) + ($earlyRate * 0.5 * 10);
        $delayScore = max(0, 100 - $asymmetricPenalty);

        return (0.55 * $cancellationScore) + (0.45 * $delayScore);
    }

    /**
     * Sigmoid penalty function: creates a cliff at threshold θ.
     * f(x) = 1 / (1 + e^(-k * (x - θ)))
     */
    private static function sigmoidPenalty(float $rate, float $theta, float $k): float
    {
        $exponent = -$k * ($rate - $theta);
        $exponent = max(-700, min(700, $exponent));

        return 1 / (1 + exp($exponent));
    }

    // ──────────────────────────────────────────────
    //  Rider Feedback Score  (§3.2, §3.3)
    //  Uses EMA rider rating from orders table
    // ──────────────────────────────────────────────

    private static function scoreRiderFeedback(Restaurant $r): float
    {
        $ema = $r->ema_rider_rating;

        if ($ema !== null && $ema > 0) {
            return $ema * 20;
        }

        $avg = (float) Order::where('restaurant_id', $r->id)
            ->whereNotNull('rider_rating_for_restaurant')
            ->avg('rider_rating_for_restaurant');

        return $avg > 0 ? $avg * 20 : 60;
    }

    // ──────────────────────────────────────────────
    //  Thompson Sampling Exploration  (§4.2)
    //  Beta(α, β) distribution sampling
    // ──────────────────────────────────────────────

    private static function scoreThompsonSampling(Restaurant $r): float
    {
        $alpha = max(1, $r->beta_alpha ?? 1);
        $beta = max(1, $r->beta_beta ?? 1);

        $sample = self::sampleBeta($alpha, $beta);

        // Scale to 0-100 range
        $score = $sample * 100;

        // Add freshness bonus for very new restaurants
        $isNew = $r->created_at && $r->created_at->diffInDays(now()) <= self::FRESHNESS_DAYS;
        if ($isNew) {
            $score += 20;
        }

        return min(100, $score);
    }

    /**
     * Sample from Beta(α, β) using Gamma distribution approximation.
     * Uses the fact that Beta(a,b) ≈ Gamma(a,1) / (Gamma(a,1) + Gamma(b,1))
     */
    private static function sampleBeta(int $alpha, int $beta): float
    {
        $x = self::sampleGamma($alpha);
        $y = self::sampleGamma($beta);
        if ($x + $y <= 0) {
            return 0.5;
        }

        return $x / ($x + $y);
    }

    /**
     * Marsaglia-Tsang method for Gamma sampling.
     */
    private static function sampleGamma(int $shape): float
    {
        if ($shape < 1) {
            $shape = 1;
        }

        $d = $shape - 1.0 / 3.0;
        $c = 1.0 / sqrt(9.0 * $d);

        while (true) {
            $x = self::sampleNormal();
            $v = 1.0 + $c * $x;
            if ($v <= 0) {
                continue;
            }

            $v = $v * $v * $v;
            $u = mt_rand() / mt_getrandmax();

            if ($u < 1.0 - 0.0331 * ($x * $x * $x * $x)) {
                break;
            }
            if (log($u) < 0.5 * $x * $x + $d * (1.0 - $v + log($v))) {
                break;
            }
        }

        return $d * $v;
    }

    /**
     * Box-Muller transform for Normal(0,1) sampling.
     */
    private static function sampleNormal(): float
    {
        $u1 = mt_rand() / (mt_getrandmax() + 1.0);
        $u2 = mt_rand() / (mt_getrandmax() + 1.0);
        if ($u1 < 1e-10) {
            $u1 = 1e-10;
        }

        return sqrt(-2.0 * log($u1)) * cos(2.0 * M_PI * $u2);
    }

    // ──────────────────────────────────────────────
    //  GSP Auction for Sponsored Listings  (§5.2)
    //  eCPM = bid × CTR × CVR; charged at 2nd price
    // ──────────────────────────────────────────────

    private static function scoreSponsoredGSP(Restaurant $r, ?float $userLat, ?float $userLng, ?int $userId): float
    {
        $listing = SponsoredListing::where('restaurant_id', $r->id)
            ->eligible()
            ->whereColumn('current_spend', '<', 'daily_budget')
            ->first();

        if (! $listing || ($listing->cpc <= 0 && $listing->bid_amount <= 0)) {
            return 0;
        }

        $bidAmount = $listing->cpc > 0 ? $listing->cpc : $listing->bid_amount;

        // ── New user filter ──
        if ($listing->target_customers === 'new_users' && $userId) {
            $orderCount = Order::where('user_id', $userId)->whereIn('status', ['delivered', 'completed'])->count();
            if ($orderCount > 0) {
                return 0;
            }
        }

        // ── Relevance threshold check ──
        $organicAffinity = ($r->rating ?? 4.0) + (($r->total_orders_completed ?? 0) > 50 ? 0.5 : 0);
        if ($organicAffinity < 3.0) {
            return 0;
        }

        // ── Estimate CTR & CVR ──
        $ctr = $listing->ctr_estimate > 0
            ? $listing->ctr_estimate
            : min(0.15, max(0.01, (($r->total_orders_completed ?? 0) / max(1, ($r->total_impressions ?? 100))) * 0.8));

        $cvr = $listing->cvr_estimate > 0
            ? $listing->cvr_estimate
            : self::estimateCvr($r);

        // ── Rank Score = CPC/CPM bid × CTR × CVR ──
        $score = $bidAmount * $ctr * $cvr;

        // ── Budget pacing ──
        $spendRatio = $listing->daily_budget > 0
            ? $listing->current_spend / $listing->daily_budget
            : 0;

        if ($spendRatio > 0.8) {
            $score *= 0.5;
        } elseif ($spendRatio > 0.5) {
            $score *= 0.8;
        }

        // ── Peak hours boost for relevance ──
        $hour = (int) now()->format('H');
        if (! (($hour >= 12 && $hour <= 14) || ($hour >= 19 && $hour <= 22))) {
            $score *= 0.7;
        }

        return $score;
    }

    private static function estimateCvr(Restaurant $r): float
    {
        $completed = Order::where('restaurant_id', $r->id)
            ->whereIn('status', ['delivered', 'completed'])->count();
        $total = max(1, Order::where('restaurant_id', $r->id)->count());

        return min(0.5, max(0.01, $completed / $total));
    }

    // ──────────────────────────────────────────────
    //  Inject sponsored listings into podium slots
    //  (§5.1)
    // ──────────────────────────────────────────────

    private static function injectSponsoredPodium(array $ranked, $scored, ?float $lat, ?float $lng, ?int $userId): array
    {
        $sponsored = SponsoredListing::where('status', 'active')
            ->where('end_date', '>=', now()->toDateString())
            ->whereColumn('current_spend', '<', 'daily_budget')
            ->where('bid_amount', '>', 0)
            ->get()
            ->keyBy('restaurant_id');

        if ($sponsored->isEmpty()) {
            return $ranked;
        }

        $sponsoredCandidates = [];

        foreach ($ranked as $restaurant) {
            if (isset($sponsored[$restaurant->id])) {
                $listing = $sponsored[$restaurant->id];
                $sponsoredCandidates[] = [
                    'restaurant' => $restaurant,
                    'ecpm' => $listing->bid_amount * max(0.01, $listing->ctr_estimate ?: 0.05) * max(0.01, $listing->cvr_estimate ?: 0.3),
                ];
            }
        }

        usort($sponsoredCandidates, fn ($a, $b) => $b['ecpm'] <=> $a['ecpm']);

        $result = $ranked;
        $usedRestaurants = [];

        foreach (self::SPONSORED_SLOTS as $slotIndex => $position) {
            if ($position >= count($result)) {
                break;
            }
            if (! isset($sponsoredCandidates[$slotIndex])) {
                break;
            }

            $candidate = $sponsoredCandidates[$slotIndex];
            $restId = $candidate['restaurant']->id;

            if (in_array($restId, $usedRestaurants)) {
                continue;
            }

            // Find current index of this restaurant
            $currentIdx = null;
            foreach ($result as $idx => $r) {
                if ($r->id === $restId) {
                    $currentIdx = $idx;
                    break;
                }
            }

            if ($currentIdx === null) {
                continue;
            }

            // Remove from current position and insert at sponsored slot
            $item = array_splice($result, $currentIdx, 1)[0];
            array_splice($result, $position, 0, [$item]);
            $usedRestaurants[] = $restId;
        }

        return $result;
    }

    // ──────────────────────────────────────────────
    //  Batching Compatibility Score  (§6.1)
    // ──────────────────────────────────────────────

    private static function scoreBatchingCompatibility(Restaurant $r): float
    {
        $score = $r->batching_score ?? 0.50;

        return $score * 100;
    }

    // ──────────────────────────────────────────────
    //  Personalization & Context  (§2.1, §2.2)
    // ──────────────────────────────────────────────

    private static function scorePersonalization(Restaurant $r, array $preferences, int $hour, bool $isWeekend): float
    {
        $score = 1.0;

        $restaurantCategories = $r->menuItems()
            ->where('is_available', true)
            ->with('globalCategory')
            ->get()
            ->pluck('globalCategory.name')
            ->unique()
            ->filter()
            ->values()
            ->toArray();

        $matched = ! empty($preferences) && ! empty($restaurantCategories)
            ? count(array_intersect($preferences, $restaurantCategories))
            : 0;

        if ($matched > 0) {
            $score += 0.15 * min(3, $matched);
        }

        $favoritesCount = DB::table('favorites')
            ->where('restaurant_id', $r->id)
            ->count();
        if ($favoritesCount > 50) {
            $score += 0.10;
        }

        // Weekend → slightly boost experiential (higher AOV) restaurants
        if ($isWeekend && ($r->total_revenue ?? 0) / max(1, $r->total_orders_completed ?? 1) > 300) {
            $score += 0.05;
        }

        return min(1.5, max(0.5, $score));
    }

    // ──────────────────────────────────────────────
    //  User Category Preferences
    // ──────────────────────────────────────────────

    private static function getUserCategoryPreferences(?int $userId): array
    {
        if (! $userId) {
            return [];
        }

        return DB::table('orders')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->join('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')
            ->join('global_categories', 'global_categories.id', '=', 'menu_items.global_category_id')
            ->where('orders.user_id', $userId)
            ->whereNotNull('global_categories.name')
            ->select('global_categories.name', DB::raw('COUNT(*) as cnt'))
            ->groupBy('global_categories.name')
            ->orderByDesc('cnt')
            ->limit(5)
            ->pluck('name')
            ->toArray();
    }

    // ──────────────────────────────────────────────
    //  Diversity Injection
    // ──────────────────────────────────────────────

    private static function injectDiversity(array $ranked): array
    {
        if (empty($ranked)) {
            return $ranked;
        }

        $ids = collect($ranked)->pluck('id')->toArray();
        $allMenuItems = MenuItem::whereIn('restaurant_id', $ids)
            ->where('is_available', true)
            ->with('globalCategory')
            ->get()
            ->groupBy('restaurant_id');

        $categoriesSeen = [];

        foreach ($ranked as &$r) {
            $penalty = 1.0;

            $items = $allMenuItems->get($r->id, collect());
            $cats = $items->pluck('globalCategory.name')
                ->unique()
                ->filter()
                ->values()
                ->toArray();

            foreach ($cats as $cat) {
                if (in_array($cat, $categoriesSeen)) {
                    $penalty *= self::DIVERSITY_PENALTY;
                }
            }

            if ($penalty < 1.0) {
                $r->_ranking_score *= $penalty;
            }

            $categoriesSeen = array_values(array_unique(array_merge($categoriesSeen, $cats)));
        }

        usort($ranked, fn ($a, $b) => $b->_ranking_score <=> $a->_ranking_score);

        return $ranked;
    }

    // ──────────────────────────────────────────────
    //  Public Helpers
    // ──────────────────────────────────────────────

    /**
     * Update EMA consumer rating when a customer rates a restaurant.
     */
    public static function updateConsumerEma(Restaurant $restaurant, float $newRating): void
    {
        $total = max(1, Order::where('restaurant_id', $restaurant->id)->count());

        if ($total >= self::HIGH_VOLUME_THRESHOLD) {
            $alpha = self::EMA_ALPHA_HIGH_VOLUME;
        } elseif ($total < self::NEW_RESTAURANT_THRESHOLD) {
            $alpha = self::EMA_ALPHA_NEW;
        } else {
            $alpha = self::EMA_ALPHA_DEFAULT;
        }

        $oldEma = $restaurant->ema_consumer_rating ?? $restaurant->rating ?? 4.0;
        $newEma = ($alpha * $newRating) + ((1 - $alpha) * $oldEma);
        $restaurant->update(['ema_consumer_rating' => round($newEma, 2)]);
    }

    /**
     * Update EMA rider rating when a rider rates a restaurant.
     */
    public static function updateRiderEma(Restaurant $restaurant, float $newRating): void
    {
        $total = max(1, Order::where('restaurant_id', $restaurant->id)->count());

        $alpha = $total >= self::HIGH_VOLUME_THRESHOLD
            ? self::EMA_ALPHA_HIGH_VOLUME
            : ($total < self::NEW_RESTAURANT_THRESHOLD ? self::EMA_ALPHA_NEW : self::EMA_ALPHA_DEFAULT);

        $oldEma = $restaurant->ema_rider_rating ?? $restaurant->rating ?? 4.0;
        $newEma = ($alpha * $newRating) + ((1 - $alpha) * $oldEma);
        $restaurant->update(['ema_rider_rating' => round($newEma, 2)]);
    }

    /**
     * Update Thompson Sampling counts when an order is completed.
     */
    public static function recordOrderOutcome(Restaurant $restaurant, bool $completed): void
    {
        if ($completed) {
            $restaurant->increment('beta_alpha');
        } else {
            $restaurant->increment('beta_beta');
        }
    }

    /**
     * Update impression count.
     */
    public static function recordImpression(Restaurant $restaurant): void
    {
        $restaurant->increment('total_impressions');
    }

    /**
     * Update batching score (called periodically or after order completion).
     */
    public static function recalculateBatchingScore(Restaurant $restaurant): float
    {
        $recentOrders = Order::where('restaurant_id', $restaurant->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->get();

        if ($recentOrders->isEmpty()) {
            $score = 0.50;
        } else {
            // Variance in prep time (lower is better for batching)
            $prepTimes = $recentOrders->pluck('prep_time_minutes')->filter();
            $variance = $prepTimes->count() > 1
                ? $prepTimes->variance()
                : 0;

            $restaurant->update(['prep_time_variance' => round($variance, 2)]);

            // Nearby order density: how many other restaurants with recent orders within 2km?
            $nearbyRestaurants = Restaurant::where('id', '!=', $restaurant->id)
                ->where('is_active', true)
                ->get()
                ->filter(function ($other) use ($restaurant) {
                    if (! $other->latitude || ! $other->longitude) {
                        return false;
                    }
                    $dist = RiderAssignmentService::haversineDistance(
                        $restaurant->latitude, $restaurant->longitude,
                        $other->latitude, $other->longitude
                    );

                    return $dist <= 3;
                })
                ->count();

            $varianceScore = max(0, 1 - ($variance / 50));
            $densityScore = min(1, $nearbyRestaurants / 10);

            $score = (0.6 * $varianceScore) + (0.4 * $densityScore);
        }

        $restaurant->update(['batching_score' => round($score, 2)]);

        return $score;
    }
}
