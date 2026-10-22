<?php

namespace App\Services;

use App\Models\DailyOutletItemMetric;
use App\Models\DailyOutletMetric;
use App\Models\Restaurant;
use Carbon\Carbon;

class BusinessReportService
{
    public const CATEGORIES = [
        'Sales',
        'Customer experience',
        'Customer funnel',
        'Customer segmentation',
        'Ads',
        'Offers',
    ];

    public function getTrendTable(Restaurant $restaurant, string $granularity, Carbon $start, Carbon $end): array
    {
        $periods = $this->buildPeriods($granularity, $start, $end);

        $metrics = DailyOutletMetric::where('restaurant_id', $restaurant->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('overview_category', self::CATEGORIES)
            ->get();

        $grouped = $metrics->groupBy(fn ($m) => $m->overview_category . '||' . $m->metric_name);

        $rows = [];
        foreach ($grouped as $key => $rowsData) {
            [$category, $metricName] = explode('||', $key);
            $valuesByDate = [];
            foreach ($rowsData as $row) {
                $valuesByDate[$row->date->toDateString()] = (float) $row->value;
            }

            $periodValues = [];
            foreach ($periods as $period) {
                $periodValues[] = $this->sumForPeriod($valuesByDate, $granularity, $period);
            }

            $rows[] = [
                'category' => $category,
                'metric' => $metricName,
                'values' => $periodValues,
            ];
        }

        usort($rows, fn ($a, $b) => array_search($a['category'], self::CATEGORIES) <=> array_search($b['category'], self::CATEGORIES));

        return [
            'periods' => $periods,
            'rows' => $rows,
        ];
    }

    public function exportDetailedReport(Restaurant $restaurant, string $granularity, Carbon $start, Carbon $end): string
    {
        $data = $this->getTrendTable($restaurant, $granularity, $start, $end);

        $headers = ['Restaurant ID', 'Restaurant name', 'Subzone', 'City', 'Overview', 'Metric'];
        foreach ($data['periods'] as $period) {
            $headers[] = $period['label'];
        }

        $rows = [];
        foreach ($data['rows'] as $row) {
            $csvRow = [
                $restaurant->id,
                $restaurant->name,
                $restaurant->address ?? '',
                '',
                $row['category'],
                $row['metric'],
            ];
            foreach ($row['values'] as $val) {
                $csvRow[] = $val;
            }
            $rows[] = $csvRow;
        }

        $path = storage_path('app/reports');
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $filename = "detailed_report_{$restaurant->id}_{$start->format('Ymd')}_{$end->format('Ymd')}.csv";
        $filepath = "$path/$filename";

        $fp = fopen($filepath, 'w');
        fputcsv($fp, $headers);
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);

        return $filepath;
    }

    public function exportItemSalesReport(Restaurant $restaurant, Carbon $start, Carbon $end): string
    {
        $items = DailyOutletItemMetric::where('restaurant_id', $restaurant->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy('menu_item_id');

        $menuItems = \App\Models\MenuItem::whereIn('id', $items->keys())->get()->keyBy('id');

        $dates = [];
        $current = $start->copy();
        while ($current->lte($end)) {
            $dates[] = $current->toDateString();
            $current->addDay();
        }

        $headers = ['Item ID', 'Item name', 'HSN Code'];
        foreach ($dates as $date) {
            $headers[] = "Orders ($date)";
            $headers[] = "Revenue ($date)";
        }

        $rows = [];
        foreach ($items as $menuItemId => $rowsData) {
            $item = $menuItems->get($menuItemId);
            $csvRow = [
                $menuItemId,
                $item ? $item->name : 'Unknown',
                $item ? ($item->hsn_code ?? '') : '',
            ];
            $valuesByDate = [];
            foreach ($rowsData as $rd) {
                $valuesByDate[$rd->date->toDateString()] = $rd;
            }
            foreach ($dates as $date) {
                $rd = $valuesByDate[$date] ?? null;
                $csvRow[] = $rd ? (int) $rd->orders : 0;
                $csvRow[] = $rd ? (float) $rd->revenue : 0;
            }
            $rows[] = $csvRow;
        }

        $path = storage_path('app/reports');
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $filename = "item_sales_report_{$restaurant->id}_{$start->format('Ymd')}_{$end->format('Ymd')}.csv";
        $filepath = "$path/$filename";

        $fp = fopen($filepath, 'w');
        fputcsv($fp, $headers);
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);

        return $filepath;
    }

    private function buildPeriods(string $granularity, Carbon $start, Carbon $end): array
    {
        $periods = [];
        $current = $start->copy();

        while ($current->lte($end)) {
            switch ($granularity) {
                case 'daily':
                    $periods[] = [
                        'start' => $current->toDateString(),
                        'end' => $current->toDateString(),
                        'label' => $current->format('M d, Y'),
                    ];
                    $current->addDay();
                    break;

                case 'weekly':
                    $weekStart = $current->copy()->startOfWeek(Carbon::MONDAY);
                    $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
                    if ($weekEnd->gt($end)) {
                        $weekEnd = $end->copy();
                    }
                    $periods[] = [
                        'start' => $weekStart->toDateString(),
                        'end' => $weekEnd->toDateString(),
                        'label' => 'W' . $weekStart->weekOfYear . ' ' . $weekStart->format('M y'),
                    ];
                    $current = $weekEnd->copy()->addDay();
                    break;

                case 'monthly':
                    $monthStart = $current->copy()->startOfMonth();
                    $monthEnd = $monthStart->copy()->endOfMonth();
                    if ($monthEnd->gt($end)) {
                        $monthEnd = $end->copy();
                    }
                    $periods[] = [
                        'start' => $monthStart->toDateString(),
                        'end' => $monthEnd->toDateString(),
                        'label' => $monthStart->format('M Y'),
                    ];
                    $current = $monthEnd->copy()->addDay();
                    break;
            }
        }

        return $periods;
    }

    private function sumForPeriod(array $valuesByDate, string $granularity, array $period): float
    {
        $sum = 0;
        $start = Carbon::parse($period['start']);
        $end = Carbon::parse($period['end']);
        $current = $start->copy();
        while ($current->lte($end)) {
            $sum += $valuesByDate[$current->toDateString()] ?? 0;
            $current->addDay();
        }
        return round($sum, 2);
    }
}
