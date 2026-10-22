<?php

namespace App\Services;

use App\Models\DailyOutletItemMetric;
use App\Models\DailyOutletMetric;
use App\Models\Restaurant;
use Carbon\Carbon;

class LiveTrackingService
{
    public function getComparisonData(Restaurant $restaurant, ?Carbon $referenceDate = null): array
    {
        $referenceDate ??= Carbon::yesterday();
        $referenceDate = $referenceDate->startOfDay();

        $dayOfWeek = $referenceDate->dayOfWeek;
        $dates = [];
        $current = $referenceDate->copy();
        $count = 0;

        while ($count < 5) {
            if ($current->dayOfWeek === $dayOfWeek) {
                $dates[] = $current->copy();
                $count++;
            }
            $current->subDay();
        }

        $dates = array_reverse($dates);

        $metrics = DailyOutletMetric::where('restaurant_id', $restaurant->id)
            ->whereIn('date', array_map(fn ($d) => $d->toDateString(), $dates))
            ->get()
            ->groupBy(fn ($m) => $m->date->toDateString());

        $categories = ['Sales', 'Customer experience', 'Customer funnel', 'Ads', 'Offers'];

        $result = [];
        foreach ($categories as $category) {
            $metricsInCategory = DailyOutletMetric::where('restaurant_id', $restaurant->id)
                ->where('overview_category', $category)
                ->whereIn('date', array_map(fn ($d) => $d->toDateString(), $dates))
                ->get()
                ->groupBy('metric_name');

            $section = [
                'category' => $category,
                'metrics' => [],
            ];

            foreach ($metricsInCategory as $metricName => $rows) {
                $dataPoints = [];
                foreach ($dates as $date) {
                    $dateStr = $date->toDateString();
                    $row = $rows->firstWhere('date', $dateStr);
                    $dataPoints[] = [
                        'date' => $dateStr,
                        'value' => $row ? (float) $row->value : 0,
                    ];
                }

                $section['metrics'][] = [
                    'name' => $metricName,
                    'dataPoints' => $dataPoints,
                    'trend' => $this->computeTrend($dataPoints),
                ];
            }

            $result[] = $section;
        }

        $result[] = [
            'category' => 'Dish trends',
            'metrics' => $this->getDishTrends($restaurant, $dates),
        ];

        return $result;
    }

    public function getDishTrends(Restaurant $restaurant, array $dates): array
    {
        $items = DailyOutletItemMetric::where('restaurant_id', $restaurant->id)
            ->whereIn('date', array_map(fn ($d) => $d->toDateString(), $dates))
            ->get()
            ->groupBy('menu_item_id');

        $menuItems = \App\Models\MenuItem::whereIn('id', $items->keys())->get()->keyBy('id');

        $dishTrends = [];
        foreach ($items as $menuItemId => $rows) {
            $item = $menuItems->get($menuItemId);
            $dataPoints = [];
            foreach ($dates as $date) {
                $dateStr = $date->toDateString();
                $row = $rows->firstWhere('date', $dateStr);
                $dataPoints[] = [
                    'date' => $dateStr,
                    'orders' => $row ? (int) $row->orders : 0,
                    'revenue' => $row ? (float) $row->revenue : 0,
                ];
            }

            $dishTrends[] = [
                'menu_item_id' => $menuItemId,
                'name' => $item ? $item->name : 'Unknown',
                'dataPoints' => $dataPoints,
                'trend' => $this->computeTrend(array_map(fn ($dp) => ['value' => $dp['orders']], $dataPoints)),
            ];
        }

        return $dishTrends;
    }

    private function computeTrend(array $dataPoints): string
    {
        $values = array_map(fn ($dp) => $dp['value'] ?? 0, $dataPoints);

        $nonZero = array_filter($values, fn ($v) => $v > 0);
        if (empty($nonZero)) {
            return 'flat';
        }

        $first = $values[0];
        $last = $values[count($values) - 1];

        if ($last > $first * 1.05) {
            return 'up';
        }
        if ($last < $first * 0.95) {
            return 'down';
        }

        return 'flat';
    }
}
