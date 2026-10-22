<?php

namespace App\Services;

use App\Models\SponsoredListing;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AdAuctionService
{
    public function __construct(private AdRecommendationService $adService) {}

    /**
     * Get ranked sponsored results for a placement (listings, search, or featured-card).
     * Returns top N eligible ads ranked by GSP-style rank score.
     */
    public function getSponsoredResults(
        string $placement,
        int $limit = 3,
        ?string $customerId = null,
        ?string $restaurantId = null,
        ?string $searchQuery = null,
        bool $isNewUser = false,
    ): Collection {
        $eligible = $this->getEligibleAds($placement, $isNewUser);

        if ($eligible->isEmpty()) {
            return collect();
        }

        $scored = $eligible
            ->map(fn ($ad) => $this->scoreAd($ad, $restaurantId, $searchQuery))
            ->filter(fn ($scored) => $scored['rank_score'] > 0)
            ->sortByDesc('rank_score')
            ->take($limit)
            ->values();

        foreach ($scored->where('ad_type', 'ppc') as $i => $winner) {
            $scored[$i]['charge_cpc'] = $this->calculateGspPrice($scored, $i, $winner);
        }

        return $scored->map(fn ($s) => [
            'listing_id'     => $s['listing_id'],
            'restaurant_id'  => $s['restaurant_id'],
            'restaurant_name' => $s['restaurant_name'],
            'ad_type'        => $s['ad_type'],
            'rank_score'     => round($s['rank_score'], 4),
            'cpc'            => $s['cpc'],
            'charge_cpc'     => $s['charge_cpc'] ?? $s['cpc'],
            'is_new_user_targeted' => $s['target_customers'] === 'new_users',
        ])->values();
    }

    /**
     * Get eligible ads for a placement, filtered by active status, time-of-day, dates.
     */
    private function getEligibleAds(string $placement, bool $isNewUser): Collection
    {
        $query = SponsoredListing::query()->eligible();

        if ($placement === 'featured') {
            $query->where('ad_type', 'card');
        } else {
            $query->where('ad_type', 'ppc')
                ->where(function ($q) use ($placement) {
                    $q->where('placement', $placement)
                      ->orWhere('placement', 'both');
                });
        }

        if ($isNewUser) {
            $query->where('target_customers', 'new_users');
        } else {
            $query->where('target_customers', 'all');
        }

        return $query->with('restaurant:id,name,cuisine_types')
            ->inRandomOrder()
            ->get();
    }

    /**
     * Calculate GSP-style rank score for an ad.
     * Rank Score = CPC bid × Predicted CTR × Relevance Score
     */
    public function scoreAd(
        SponsoredListing $ad,
        ?string $restaurantId = null,
        ?string $searchQuery = null,
    ): array {
        $cpc = (float) $ad->cpc;
        $predictedCtr = $this->getPredictedCtr($ad);
        $relevance = $this->getRelevanceScore($ad, $restaurantId, $searchQuery);
        $rankScore = $cpc * $predictedCtr * $relevance;

        return [
            'listing_id'       => $ad->id,
            'restaurant_id'    => $ad->restaurant_id,
            'restaurant_name'  => $ad->restaurant->name ?? '',
            'ad_type'          => $ad->ad_type,
            'rank_score'       => $rankScore,
            'cpc'              => $cpc,
            'predicted_ctr'    => $predictedCtr,
            'relevance'        => $relevance,
            'target_customers' => $ad->target_customers,
        ];
    }

    /**
     * Predicted CTR — historical CTR if data exists, otherwise cold-start average.
     */
    private function getPredictedCtr(SponsoredListing $ad): float
    {
        $performance = $ad->dailyPerformance()
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->get();

        $totalImpressions = $performance->sum('impressions');
        $totalClicks = $performance->sum('clicks');

        if ($totalImpressions > 0) {
            $ctr = $totalClicks / $totalImpressions;
            return max(0.001, $ctr);
        }

        return (float) ($ad->ctr_estimate ?? 0.02);
    }

    /**
     * Relevance score: 1.0 for listing, adjusted for search query match.
     */
    private function getRelevanceScore(
        SponsoredListing $ad,
        ?string $restaurantId = null,
        ?string $searchQuery = null,
    ): float {
        $score = 1.0;

        if ($searchQuery && $restaurantId) {
            $cuisineMatch = false;
            $cuisines = $ad->restaurant->cuisine_types ?? '';
            if (stripos($cuisines, $searchQuery) !== false) {
                $cuisineMatch = true;
            }
            if (stripos(($ad->restaurant->name ?? ''), $searchQuery) !== false) {
                $cuisineMatch = true;
            }
            $score = $cuisineMatch ? 2.0 : 0.7;
        }

        return $score;
    }

    /**
     * Generalized Second-Price (GSP) pricing:
     * Pay = (next-highest Rank Score ÷ own Quality Score) + small increment,
     *       capped at own CPC bid.
     */
    private function calculateGspPrice(Collection $scored, int $index, array $winner): float
    {
        $ownQuality = ($winner['predicted_ctr'] ?? 0.02) * ($winner['relevance'] ?? 1.0);

        if ($index + 1 < $scored->count()) {
            $next = $scored[$index + 1];
            $gspPrice = ($next['rank_score'] / max($ownQuality, 0.001)) + 0.01;
            return min(round($gspPrice, 2), $winner['cpc']);
        }

        $reserve = $winner['cpc'] * 0.3;
        return max(round($reserve, 2), 0.50);
    }

    /**
     * Get budget pacing throttle factor (0.0–1.0).
     * Prevents budget exhaustion in the first hour.
     */
    public function getPacingFactor(SponsoredListing $ad): float
    {
        $now = Carbon::now();
        $start = Carbon::parse($ad->start_date);
        $end = Carbon::parse($ad->end_date ?? $start->copy()->addWeeks($ad->duration_weeks));

        $totalSeconds = max(1, $end->diffInSeconds($start));
        $elapsedSeconds = max(1, $now->diffInSeconds($start));

        if ($now->lt($start) || $now->gt($end)) {
            return 0;
        }

        $budgetSpent = (float) $ad->current_spend;
        $budgetRemaining = max(0, (float) $ad->daily_budget - $budgetSpent);

        $timeRatio = $elapsedSeconds / $totalSeconds;
        $expectedSpend = $timeRatio * (float) $ad->daily_budget;
        $budgetRatio = $expectedSpend > 0 ? $budgetSpent / $expectedSpend : 1;

        if ($budgetRatio >= 1.0) {
            return max(0, $budgetRemaining / max(1, (float) $ad->daily_budget));
        }

        return 1.0;
    }

    /**
     * Auto-pause ads whose budget is exhausted or end date is passed.
     * Callable from scheduler. Returns count of paused ads.
     */
    public function pauseExhaustedAds(): int
    {
        $count = 0;

        SponsoredListing::where('status', 'active')->cursor()->each(function (SponsoredListing $ad) use (&$count) {
            if (now()->gt($ad->end_date)) {
                $ad->update(['status' => 'completed']);
                $count++;
                return;
            }

            if ((float) $ad->daily_budget > 0 && (float) $ad->current_spend >= (float) $ad->daily_budget) {
                $ad->update(['status' => 'paused']);
                $count++;
            }
        });

        return $count;
    }

    /**
     * Activate scheduled ads whose start date has arrived.
     */
    public function activateScheduledAds(): int
    {
        return SponsoredListing::where('status', 'scheduled')
            ->where('start_date', '<=', now()->toDateString())
            ->update(['status' => 'active']);
    }
}
