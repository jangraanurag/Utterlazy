<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\SponsoredListing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdRecommendationService
{
    /**
     * Get CPC and weekly budget recommendations for an ad.
     *
     * @param  string  $adType  ppc|card
     * @param  string|null  $placement  listings|search|both|null
     * @param  string  $targetCustomers  all|new_users
     * @param  string  $timeFrom  H:i
     * @param  string  $timeTo  H:i
     * @return array{cpc: array{min: float, max: float, recommended: float}, weekly_budget: array{min: float, max: float, recommended: float}}
     */
    public function getRecommendations(
        string $adType,
        ?string $placement,
        string $targetCustomers,
        string $restaurantId,
        bool $allDay = true,
        ?string $timeFrom = null,
        ?string $timeTo = null,
    ): array {
        $restaurant = Restaurant::find($restaurantId);

        // Query comparable active/ended ads for CPC data (same placement, recent 30 days)
        $comparableCpc = SponsoredListing::where('ad_type', $adType)
            ->where('cpc', '>', 0)
            ->where('created_at', '>=', now()->subDays(30));

        if ($placement) {
            $comparableCpc->where('placement', $placement);
        }

        $cpcValues = $comparableCpc->pluck('cpc')->toArray();
        sort($cpcValues);
        $count = count($cpcValues);

        // CPC recommendation
        if ($count >= 10) {
            $cpcMin = $cpcValues[(int) floor($count * 0.25)] ?? 2;
            $cpcMax = $cpcValues[(int) floor($count * 0.75)] ?? 10;
        } else {
            // Cold start defaults
            $cpcMin = 2;
            $cpcMax = 8;
        }

        // Adjust for time-of-day demand
        $demandMultiplier = $this->getDemandMultiplier($allDay, $timeFrom, $timeTo);
        $cpcMin = round($cpcMin * $demandMultiplier, 2);
        $cpcMax = round($cpcMax * $demandMultiplier, 2);

        // Adjust for new users (typically lower competition → lower CPC)
        if ($targetCustomers === 'new_users') {
            $cpcMin = round($cpcMin * 0.8, 2);
            $cpcMax = round($cpcMax * 0.8, 2);
        }

        $recommendedCpc = round(($cpcMin + $cpcMax) / 2, 2);

        // Weekly budget recommendation
        // Estimate ~30-50 clicks per week as minimum viable
        $expectedClicksPerWeek = 40;
        $budgetMin = round($expectedClicksPerWeek * $cpcMin * 0.7, 2);
        $budgetMax = round($expectedClicksPerWeek * $cpcMax * 1.3, 2);
        $budgetRecommended = round($budgetMin + ($budgetMax - $budgetMin) * 0.4, 2);

        return [
            'cpc' => [
                'min' => $cpcMin,
                'max' => $cpcMax,
                'recommended' => $recommendedCpc,
            ],
            'weekly_budget' => [
                'min' => max($budgetMin, 100),
                'max' => round($budgetMax, -1),
                'recommended' => round($budgetRecommended, -1),
            ],
            'is_cold_start' => $count < 10,
        ];
    }

    /**
     * Demand multiplier based on time-of-day.
     * Peak meal hours (12-2pm, 7-10pm) have higher competition → higher CPC.
     */
    private function getDemandMultiplier(bool $allDay, ?string $timeFrom, ?string $timeTo): float
    {
        if ($allDay) {
            return 1.0;
        }

        $from = $timeFrom ? (int) explode(':', $timeFrom)[0] : 0;
        $to = $timeTo ? (int) explode(':', $timeTo)[0] : 23;

        // Check if the window overlaps with peak hours
        $peakMorning = range(12, 14);
        $peakEvening = range(19, 22);

        $overlapsPeak = false;
        for ($h = $from; $h <= $to; $h++) {
            if (in_array($h, $peakMorning) || in_array($h, $peakEvening)) {
                $overlapsPeak = true;
                break;
            }
        }

        return $overlapsPeak ? 1.3 : 0.8;
    }

    /**
     * Log an impression event.
     */
    public function logImpression(string $listingId, string $customerId): void
    {
        $today = now()->toDateString();
        AdPerformanceDaily::updateOrCreate(
            ['listing_id' => $listingId, 'date' => $today],
            ['impressions' => DB::raw('impressions + 1')]
        );
    }

    /**
     * Log a click event with fraud deduplication.
     * Returns false if click is flagged as duplicate.
     */
    public function logClick(string $listingId, string $customerId, string $sessionId): bool
    {
        $cacheKey = "ad_click_{$listingId}_{$customerId}_{$sessionId}";
        if (Cache::has($cacheKey)) {
            return false; // Duplicate click within window
        }
        Cache::put($cacheKey, true, now()->addMinutes(2));

        $today = now()->toDateString();
        AdPerformanceDaily::updateOrCreate(
            ['listing_id' => $listingId, 'date' => $today],
            ['clicks' => DB::raw('clicks + 1')]
        );

        // Deduct CPC from the listing's spend
        $listing = SponsoredListing::find($listingId);
        if ($listing) {
            $listing->increment('current_spend', $listing->cpc);
        }

        return true;
    }

    /**
     * Log an attributed order for an ad.
     */
    public function logAttributedOrder(string $listingId, float $orderRevenue): void
    {
        $today = now()->toDateString();
        AdPerformanceDaily::updateOrCreate(
            ['listing_id' => $listingId, 'date' => $today],
            [
                'attributed_orders' => DB::raw('attributed_orders + 1'),
                'revenue' => DB::raw("revenue + {$orderRevenue}"),
            ]
        );
    }
}
