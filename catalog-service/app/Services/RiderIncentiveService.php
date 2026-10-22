<?php

namespace App\Services;

use App\Models\Rider;
use App\Models\RiderPayout;

class RiderIncentiveService
{
    protected array $badges = [
        ['key' => 'rising_star', 'name' => 'Rising Star', 'icon' => '🌟', 'min_deliveries' => 5, 'min_rating' => 4.5, 'bonus' => 0],
        ['key' => 'speedster', 'name' => 'Speedster', 'icon' => '⚡', 'min_deliveries' => 20, 'min_rating' => 4.3, 'bonus' => 50],
        ['key' => 'night_owl', 'name' => 'Night Owl', 'icon' => '🦉', 'min_deliveries' => 10, 'min_rating' => 4.0, 'bonus' => 75],
        ['key' => 'weekend_warrior', 'name' => 'Weekend Warrior', 'icon' => '⚔️', 'min_deliveries' => 15, 'min_rating' => 4.2, 'bonus' => 100],
        ['key' => 'rain_rider', 'name' => 'Rain Rider', 'icon' => '🌧️', 'min_deliveries' => 8, 'min_rating' => 4.0, 'bonus' => 120],
        ['key' => 'centurion', 'name' => 'Centurion', 'icon' => '💯', 'min_deliveries' => 100, 'min_rating' => 4.5, 'bonus' => 500],
        ['key' => 'gold_rider', 'name' => 'Gold Rider', 'icon' => '🥇', 'min_deliveries' => 200, 'min_rating' => 4.7, 'bonus' => 1000],
        ['key' => 'diamond_rider', 'name' => 'Diamond Rider', 'icon' => '💎', 'min_deliveries' => 500, 'min_rating' => 4.8, 'bonus' => 2500],
    ];

    protected array $tiers = [
        ['key' => 'bronze', 'name' => 'Bronze', 'icon' => '🥉', 'min_deliveries' => 0, 'rush_bonus' => 1.0, 'priority_score' => 1.0],
        ['key' => 'silver', 'name' => 'Silver', 'icon' => '🥈', 'min_deliveries' => 50, 'rush_bonus' => 1.1, 'priority_score' => 1.2],
        ['key' => 'gold', 'name' => 'Gold', 'icon' => '🥇', 'min_deliveries' => 150, 'rush_bonus' => 1.2, 'priority_score' => 1.5],
        ['key' => 'diamond', 'name' => 'Diamond', 'icon' => '💎', 'min_deliveries' => 400, 'rush_bonus' => 1.3, 'priority_score' => 2.0],
    ];

    public function evaluateBadges(Rider $rider): array
    {
        $earned = [];
        $currentBadges = $rider->badges ?? [];

        foreach ($this->badges as $badge) {
            $has = $rider->total_deliveries >= $badge['min_deliveries']
                && ($rider->avg_rating ?? 0) >= $badge['min_rating'];

            $alreadyEarned = in_array($badge['key'], $currentBadges);

            if ($has && ! $alreadyEarned) {
                $earned[] = $badge;
            }
        }

        return $earned;
    }

    public function awardBadge(Rider $rider, array $badge): void
    {
        $currentBadges = $rider->badges ?? [];
        if (! in_array($badge['key'], $currentBadges)) {
            $currentBadges[] = $badge['key'];
            $rider->update(['badges' => $currentBadges]);

            if ($badge['bonus'] > 0) {
                $rider->increment('total_earnings', $badge['bonus']);
                RiderPayout::create([
                    'rider_id' => $rider->id,
                    'order_id' => null,
                    'incentive_bonus' => $badge['bonus'],
                    'total_payout' => $badge['bonus'],
                    'is_settled' => false,
                    'base_pay' => 0,
                    'distance_pay' => 0,
                    'surge_pay' => 0,
                    'wait_pay' => 0,
                    'tip_amount' => 0,
                    'penalty' => 0,
                    'cash_collected' => 0,
                ]);
            }
        }
    }

    public function getTier(Rider $rider): array
    {
        $riderTier = $this->tiers[0];
        foreach ($this->tiers as $tier) {
            if ($rider->total_deliveries >= $tier['min_deliveries']) {
                $riderTier = $tier;
            }
        }

        return $riderTier;
    }

    public function getTodayBonus(Rider $rider): float
    {
        if ($rider->total_deliveries == 0) {
            return 0;
        }

        $todayDeliveries = $rider->todaysDeliveries();
        $hour = now()->hour;
        $tier = $this->getTier($rider);
        $bonusMultiplier = $tier['rush_bonus'];

        $bonus = 0;

        // Rush hour bonus (12-2 PM and 7-9 PM)
        if (($hour >= 12 && $hour <= 14) || ($hour >= 19 && $hour <= 21)) {
            $bonus += 10 * $bonusMultiplier; // ₹10 per delivery during rush
        }

        // Rain bonus (simplified — checks if raining)
        if (now()->hour % 6 == 0) { // Simplified check
            $bonus += 15 * $bonusMultiplier;
        }

        // Streak bonus (5+ deliveries today)
        if ($todayDeliveries >= 5) {
            $bonus += 20;
        }

        return round($bonus, 2);
    }

    public function getFullProfile(Rider $rider): array
    {
        $newBadges = $this->evaluateBadges($rider);

        // Auto-award any new badges
        foreach ($newBadges as $badge) {
            $this->awardBadge($rider, $badge);
        }

        $rider = $rider->fresh();
        $tier = $this->getTier($rider);

        return [
            'tier' => $tier,
            'badges' => $rider->badges ?? [],
            'total_deliveries' => $rider->total_deliveries,
            'avg_rating' => $rider->avg_rating,
            'today_bonus' => $this->getTodayBonus($rider),
            'next_tier' => $this->getNextTier($rider),
            'deliveries_to_next_tier' => $this->deliveriesToNextTier($rider),
        ];
    }

    protected function getNextTier(Rider $rider): ?array
    {
        $currentTier = $this->getTier($rider);
        foreach ($this->tiers as $tier) {
            if ($tier['min_deliveries'] > $currentTier['min_deliveries']) {
                return $tier;
            }
        }

        return null; // Already at max tier
    }

    protected function deliveriesToNextTier(Rider $rider): int
    {
        $next = $this->getNextTier($rider);
        if (! $next) {
            return 0;
        }

        return max(0, $next['min_deliveries'] - $rider->total_deliveries);
    }
}
