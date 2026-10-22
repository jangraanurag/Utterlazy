<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantRating;
use App\Services\Ranker\RankerFeatureBuilder;
use App\Services\RiderAssignmentService;
use Illuminate\Console\Command;

/**
 * Exports a labeled ranking dataset (positives = delivered orders, negatives =
 * same-city restaurants the user did not order from).
 *
 * v0 caveat: popularity/cancellation features are computed at export time, not
 * order time. Use ClickHouse time-travel features before training production
 * models. The exporter is for offline experimentation only.
 */
class ExportRankerDataset extends Command
{
    protected $signature = 'ranker:export-dataset
                            {--days=30 : Delivered-order window}
                            {--limit=5000 : Max positive orders}
                            {--negatives=3 : Negatives per positive}
                            {--out= : CSV output path (default storage/app/ranker/dataset_<ts>.csv)}';

    protected $description = 'Export a labeled CSV dataset for the organic ranker';

    public function handle(RankerFeatureBuilder $builder): int
    {
        $days = (int) $this->option('days');
        $limit = (int) $this->option('limit');
        $negatives = max(0, (int) $this->option('negatives'));

        $orders = Order::with(['restaurant:id,name,city_id,rating,latitude,longitude,avg_prep_time_minutes,price_for_two,total_orders_completed'])
            ->where('status', 'delivered')
            ->where('created_at', '>=', now()->subDays($days))
            ->whereNotNull('restaurant_id')
            ->whereNotNull('delivery_address_lat')
            ->whereNotNull('delivery_address_lng')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        if ($orders->isEmpty()) {
            $this->warn('No delivered orders with coordinates in the window.');

            return self::SUCCESS;
        }

        $cityIds = $orders->pluck('restaurant.city_id')->filter()->unique()->values()->all();

        $restaurants = Restaurant::query()
            ->where('is_active', true)
            ->when($cityIds, fn ($q) => $q->whereIn('city_id', $cityIds))
            ->withCount('ratings')
            ->get(['id', 'city_id', 'rating', 'latitude', 'longitude', 'avg_prep_time_minutes',
                'price_for_two', 'total_orders_completed'])
            ->keyBy('id');

        $ids = $restaurants->keys()->all();

        $weekOrders = Order::whereIn('restaurant_id', $ids)
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('restaurant_id, COUNT(*) AS c')
            ->groupBy('restaurant_id')
            ->pluck('c', 'restaurant_id');

        $monthStats = Order::whereIn('restaurant_id', $ids)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw("restaurant_id, COUNT(*) AS total, SUM(status = 'cancelled') AS cancelled")
            ->groupBy('restaurant_id')
            ->get()
            ->keyBy('restaurant_id');

        $byCity = $restaurants->groupBy('city_id');
        $orderedByUser = $orders->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('restaurant_id')->all())
            ->all();

        $path = $this->option('out')
            ?: storage_path('app/ranker/dataset_'.now()->format('Ymd_His').'.csv');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $handle = fopen($path, 'w');
        if ($handle === false) {
            $this->error("Cannot write {$path}");

            return self::FAILURE;
        }

        fputcsv($handle, $builder->csvHeader(), ',', '"', '');

        mt_srand(42);
        $rows = 0;

        foreach ($orders as $order) {
            $restaurant = $restaurants->get($order->restaurant_id);
            if (! $restaurant) {
                continue;
            }

            $distance = RiderAssignmentService::haversineDistance(
                (float) $order->delivery_address_lat,
                (float) $order->delivery_address_lng,
                (float) $restaurant->latitude,
                (float) $restaurant->longitude,
            );

            fputcsv($handle, $this->row(1, $order->restaurant_id, $restaurant->city_id,
                $this->featureInput($restaurant, $distance, $weekOrders, $monthStats, $builder), $builder), ',', '"', '');
            $rows++;

            if ($negatives === 0) {
                continue;
            }

            $candidates = collect($byCity->get($restaurant->city_id, []))
                ->reject(fn ($r) => $r->id === $order->restaurant_id
                    || in_array($r->id, $orderedByUser[$order->user_id] ?? [], true))
                ->shuffle()
                ->take($negatives);

            foreach ($candidates as $candidate) {
                $negDistance = RiderAssignmentService::haversineDistance(
                    (float) $order->delivery_address_lat,
                    (float) $order->delivery_address_lng,
                    (float) $candidate->latitude,
                    (float) $candidate->longitude,
                );

                fputcsv($handle, $this->row(0, $candidate->id, $candidate->city_id,
                    $this->featureInput($candidate, $negDistance, $weekOrders, $monthStats, $builder), $builder), ',', '"', '');
                $rows++;
            }
        }

        fclose($handle);

        $this->info("Wrote {$rows} rows to {$path}");

        return self::SUCCESS;
    }

    private function featureInput($restaurant, float $distance, $weekOrders, $monthStats, RankerFeatureBuilder $builder): array
    {
        $stats = $monthStats->get($restaurant->id);

        return [
            'rating' => (float) ($restaurant->rating ?? 0),
            'rating_count' => (int) ($restaurant->ratings_count ?? 0),
            'distance_km' => $distance,
            'prep_time_minutes' => (float) ($restaurant->avg_prep_time_minutes ?? 20),
            'order_count_7d' => (int) ($weekOrders[$restaurant->id] ?? 0),
            'cancelled_30d' => (int) ($stats->cancelled ?? 0),
            'orders_30d' => (int) ($stats->total ?? 0),
            'avg_price' => (float) ($restaurant->price_for_two ?? 0),
            'total_orders' => (int) ($restaurant->total_orders_completed ?? 0),
        ];
    }

    private function row(int $label, string $restaurantId, ?string $cityId, array $features, RankerFeatureBuilder $builder): array
    {
        $values = $builder->features($features);

        return array_merge([$label, $restaurantId, (string) $cityId], array_values($values));
    }
}
