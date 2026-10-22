<?php

namespace App\Services\Ranker;

/**
 * Pure feature math shared by the dataset exporter and tests.
 * Keep this in sync with services/ranker/app/ranking.py.
 */
class RankerFeatureBuilder
{
    public const PRIOR_MEAN = 4.0;

    public const PRIOR_WEIGHT = 20.0;

    public const FEATURES = [
        'bayes_rating',
        'rating_count',
        'distance_km',
        'prep_time_minutes',
        'order_count_7d',
        'cancellation_rate',
        'avg_price',
        'is_new',
    ];

    public function bayesRating(float $rating, int $ratingCount): float
    {
        return round(
            (($rating * $ratingCount) + (self::PRIOR_MEAN * self::PRIOR_WEIGHT)) / ($ratingCount + self::PRIOR_WEIGHT),
            6,
        );
    }

    public function cancellationRate(int $cancelled, int $total): float
    {
        return $total > 0 ? round($cancelled / $total, 6) : 0.0;
    }

    /**
     * @param array<string, mixed> $in
     * @return array<string, float|int>
     */
    public function features(array $in): array
    {
        $ratingCount = (int) ($in['rating_count'] ?? 0);
        $totalOrders = (int) ($in['total_orders'] ?? 0);

        return [
            'bayes_rating' => $this->bayesRating((float) ($in['rating'] ?? 0), $ratingCount),
            'rating_count' => $ratingCount,
            'distance_km' => round((float) ($in['distance_km'] ?? 0), 6),
            'prep_time_minutes' => round((float) ($in['prep_time_minutes'] ?? 20), 3),
            'order_count_7d' => (int) ($in['order_count_7d'] ?? 0),
            'cancellation_rate' => $this->cancellationRate(
                (int) ($in['cancelled_30d'] ?? 0),
                (int) ($in['orders_30d'] ?? 0),
            ),
            'avg_price' => round((float) ($in['avg_price'] ?? 0), 2),
            'is_new' => ($totalOrders < 20 || $ratingCount < 20) ? 1 : 0,
        ];
    }

    /** @return string[] */
    public function csvHeader(): array
    {
        return array_merge(['label', 'restaurant_id', 'city_id'], self::FEATURES);
    }
}
