<?php

namespace App\Services;

use App\Models\DailyOutletItemMetric;
use App\Models\DailyOutletMetric;
use App\Models\Order;
use App\Models\Restaurant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MetricAggregationService
{
    public function aggregateForRestaurant(Restaurant $restaurant, Carbon $date): void
    {
        $this->aggregateSalesMetrics($restaurant, $date);
        $this->aggregateCustomerExperienceMetrics($restaurant, $date);
        $this->aggregateFunnelMetrics($restaurant, $date);
        $this->aggregateSegmentationMetrics($restaurant, $date);
        $this->aggregateAdsMetrics($restaurant, $date);
        $this->aggregateOffersMetrics($restaurant, $date);
        $this->aggregateItemMetrics($restaurant, $date);
    }

    public function aggregateForAllRestaurants(Carbon $date): void
    {
        Restaurant::where('is_active', true)->chunk(50, function ($restaurants) use ($date) {
            foreach ($restaurants as $restaurant) {
                $this->aggregateForRestaurant($restaurant, $date);
            }
        });
    }

    public function aggregateForDateRange(Carbon $start, Carbon $end): void
    {
        $date = $start->copy();
        while ($date->lte($end)) {
            $this->aggregateForAllRestaurants($date);
            $date->addDay();
        }
    }

    private function upsertMetric(Restaurant $restaurant, Carbon $date, string $category, string $metric, float $value): void
    {
        DailyOutletMetric::updateOrCreate(
            [
                'restaurant_id' => $restaurant->id,
                'date' => $date->toDateString(),
                'overview_category' => $category,
                'metric_name' => $metric,
            ],
            ['value' => round($value, 2)]
        );
    }

    private function aggregateSalesMetrics(Restaurant $restaurant, Carbon $date): void
    {
        $deliveredOrders = Order::where('restaurant_id', $restaurant->id)
            ->whereDate('created_at', $date)
            ->where('status', 'delivered')
            ->get();

        $deliveredCount = $deliveredOrders->count();
        $sales = $deliveredOrders->sum('subtotal');

        $this->upsertMetric($restaurant, $date, 'Sales', 'Delivered orders', $deliveredCount);
        $this->upsertMetric($restaurant, $date, 'Sales', 'Sales (Rs)', $sales);
        $this->upsertMetric($restaurant, $date, 'Sales', 'Market share (%)', 0);
    }

    private function aggregateCustomerExperienceMetrics(Restaurant $restaurant, Carbon $date): void
    {
        $reviews = DB::table('reviews')
            ->where('restaurant_id', $restaurant->id)
            ->whereDate('created_at', $date)
            ->selectRaw('COUNT(*) as total, AVG(rating) as avg_rating, SUM(CASE WHEN rating <= 2 THEN 1 ELSE 0 END) as bad_orders')
            ->first();

        $ratedOrders = (int) ($reviews->total ?? 0);
        $avgRating = (float) ($reviews->avg_rating ?? 0);
        $badOrders = (int) ($reviews->bad_orders ?? 0);

        $rejectedOrders = Order::where('restaurant_id', $restaurant->id)
            ->whereDate('created_at', $date)
            ->where('status', 'cancelled')
            ->count();

        $delayedOrders = Order::where('restaurant_id', $restaurant->id)
            ->whereDate('created_at', $date)
            ->where('prep_delay_minutes', '>', 10)
            ->count();

        $complaints = DB::table('complaints')
            ->where('restaurant_id', $restaurant->id)
            ->whereDate('created_at', $date);

        $totalComplaints = (clone $complaints)->count();
        $nonRefundedComplaints = (clone $complaints)->where('refund_processed', false)->count();

        $kpt = Order::where('restaurant_id', $restaurant->id)
            ->whereDate('created_at', $date)
            ->where('status', 'delivered')
            ->whereNotNull('prep_time_minutes')
            ->avg('prep_time_minutes') ?? 0;

        $delivered = Order::where('restaurant_id', $restaurant->id)
            ->whereDate('created_at', $date)
            ->where('status', 'delivered')
            ->count();
        $cancelled = Order::where('restaurant_id', $restaurant->id)
            ->whereDate('created_at', $date)
            ->where('status', 'cancelled')
            ->count();
        $forAccuracy = $delivered + $cancelled > 0
            ? ($delivered / ($delivered + $cancelled)) * 100
            : 0;

        $this->upsertMetric($restaurant, $date, 'Customer experience', 'Average rating', $avgRating);
        $this->upsertMetric($restaurant, $date, 'Customer experience', 'Rated orders', $ratedOrders);
        $this->upsertMetric($restaurant, $date, 'Customer experience', 'Bad orders', $badOrders);
        $this->upsertMetric($restaurant, $date, 'Customer experience', 'Rejected orders', $rejectedOrders);
        $this->upsertMetric($restaurant, $date, 'Customer experience', 'KPT+10 delayed orders', $delayedOrders);
        $this->upsertMetric($restaurant, $date, 'Customer experience', 'Poor rated orders', $badOrders);
        $this->upsertMetric($restaurant, $date, 'Customer experience', 'Total complaints', $totalComplaints);
        $this->upsertMetric($restaurant, $date, 'Customer experience', 'Non-refunded complaints', $nonRefundedComplaints);
        $this->upsertMetric($restaurant, $date, 'Customer experience', 'Total complaints – Poor packaging', 0);
        $this->upsertMetric($restaurant, $date, 'Customer experience', 'Total complaints – Poor quality', 0);
        $this->upsertMetric($restaurant, $date, 'Customer experience', 'Total complaints – Wrong order', 0);
        $this->upsertMetric($restaurant, $date, 'Customer experience', 'Total complaints – Missing items', 0);
        $this->upsertMetric($restaurant, $date, 'Customer experience', 'Self logs other ors', 0);
        $this->upsertMetric($restaurant, $date, 'Customer experience', 'Lost sales (Rs)', 0);
        $this->upsertMetric($restaurant, $date, 'Customer experience', 'Online %', 100);
        $this->upsertMetric($restaurant, $date, 'Customer experience', 'Offline time (in hours)', 0);
        $this->upsertMetric($restaurant, $date, 'Customer experience', 'KPT (in minutes)', round($kpt, 1));
        $this->upsertMetric($restaurant, $date, 'Customer experience', 'FOR accuracy (%)', round($forAccuracy, 1));
    }

    private function aggregateFunnelMetrics(Restaurant $restaurant, Carbon $date): void
    {
        $placedOrders = Order::where('restaurant_id', $restaurant->id)
            ->whereDate('created_at', $date)
            ->whereIn('status', ['delivered', 'cancelled', 'out_for_delivery', 'preparing', 'confirmed'])
            ->count();

        $this->upsertMetric($restaurant, $date, 'Customer funnel', 'Impressions', 0);
        $this->upsertMetric($restaurant, $date, 'Customer funnel', 'Impressions to menu (%)', 0);
        $this->upsertMetric($restaurant, $date, 'Customer funnel', 'Menu opens', 0);
        $this->upsertMetric($restaurant, $date, 'Customer funnel', 'Menu to cart (%)', 0);
        $this->upsertMetric($restaurant, $date, 'Customer funnel', 'Cart builds', $placedOrders);
        $this->upsertMetric($restaurant, $date, 'Customer funnel', 'Cart to orders (%)', 0);
        $this->upsertMetric($restaurant, $date, 'Customer funnel', 'Placed Orders', $placedOrders);
    }

    private function aggregateSegmentationMetrics(Restaurant $restaurant, Carbon $date): void
    {
        $orders = Order::where('restaurant_id', $restaurant->id)
            ->whereDate('created_at', $date)
            ->where('status', 'delivered')
            ->get();

        $newUserOrders = 0;
        $repeatUserOrders = 0;
        $lapsedUserOrders = 0;

        foreach ($orders as $order) {
            $priorCount = Order::where('restaurant_id', $restaurant->id)
                ->where('user_id', $order->user_id)
                ->where('id', '!=', $order->id)
                ->where('status', 'delivered')
                ->where('created_at', '<', $order->created_at)
                ->count();

            if ($priorCount === 0) {
                $newUserOrders++;
            } else {
                $lastOrder = Order::where('restaurant_id', $restaurant->id)
                    ->where('user_id', $order->user_id)
                    ->where('id', '!=', $order->id)
                    ->where('status', 'delivered')
                    ->where('created_at', '<', $order->created_at)
                    ->latest('created_at')
                    ->first();

                if ($lastOrder && $lastOrder->created_at->diffInDays($order->created_at) > 30) {
                    $lapsedUserOrders++;
                } else {
                    $repeatUserOrders++;
                }
            }
        }

        $timeBuckets = [
            'Breakfast orders' => [6, 10],
            'Lunch orders' => [12, 15],
            'Snacks orders' => [16, 18],
            'Dinner orders' => [19, 22],
            'Late night orders' => [23, 5],
        ];

        foreach ($timeBuckets as $metric => [$start, $end]) {
            $count = Order::where('restaurant_id', $restaurant->id)
                ->whereDate('created_at', $date)
                ->where('status', 'delivered')
                ->whereRaw($start <= $end
                    ? "HOUR(created_at) BETWEEN $start AND $end"
                    : "(HOUR(created_at) >= $start OR HOUR(created_at) <= $end)")
                ->count();

            $this->upsertMetric($restaurant, $date, 'Customer segmentation', $metric, $count);
        }

        $this->upsertMetric($restaurant, $date, 'Customer segmentation', 'New user orders', $newUserOrders);
        $this->upsertMetric($restaurant, $date, 'Customer segmentation', 'Repeat user orders', $repeatUserOrders);
        $this->upsertMetric($restaurant, $date, 'Customer segmentation', 'Lapsed user orders', $lapsedUserOrders);
    }

    private function aggregateAdsMetrics(Restaurant $restaurant, Carbon $date): void
    {
        $adDaily = DB::table('ad_performance_daily')
            ->join('sponsored_listings', 'ad_performance_daily.listing_id', '=', 'sponsored_listings.id')
            ->where('sponsored_listings.restaurant_id', $restaurant->id)
            ->whereDate('ad_performance_daily.date', $date)
            ->selectRaw('
                COALESCE(SUM(impressions), 0) as impressions,
                COALESCE(SUM(clicks), 0) as clicks,
                COALESCE(SUM(attributed_orders), 0) as attr_orders,
                COALESCE(SUM(spend), 0) as spend,
                COALESCE(SUM(revenue), 0) as revenue
            ')
            ->first();

        $impressions = (int) ($adDaily->impressions ?? 0);
        $clicks = (int) ($adDaily->clicks ?? 0);
        $attrOrders = (int) ($adDaily->attr_orders ?? 0);
        $spend = (float) ($adDaily->spend ?? 0);
        $revenue = (float) ($adDaily->revenue ?? 0);
        $ctr = $impressions > 0 ? ($clicks / $impressions) * 100 : 0;
        $roi = $spend > 0 ? (($revenue - $spend) / $spend) * 100 : 0;

        $this->upsertMetric($restaurant, $date, 'Ads', 'Sales from ads (Rs)', $revenue);
        $this->upsertMetric($restaurant, $date, 'Ads', 'Ads CTR (%)', round($ctr, 2));
        $this->upsertMetric($restaurant, $date, 'Ads', 'Ads impressions', $impressions);
        $this->upsertMetric($restaurant, $date, 'Ads', 'Ads menu opens', 0);
        $this->upsertMetric($restaurant, $date, 'Ads', 'Ads orders', $attrOrders);
        $this->upsertMetric($restaurant, $date, 'Ads', 'Ads spend (Rs)', $spend);
        $this->upsertMetric($restaurant, $date, 'Ads', 'Ads ROI', round($roi, 2));
    }

    private function aggregateOffersMetrics(Restaurant $restaurant, Carbon $date): void
    {
        $offerOrders = Order::where('restaurant_id', $restaurant->id)
            ->whereDate('created_at', $date)
            ->where('status', 'delivered')
            ->where(function ($q) {
                $q->whereNotNull('coupon_id')
                  ->orWhere('vendor_discount_amount', '>', 0);
            })
            ->get();

        $ordersWithOffers = $offerOrders->count();
        $grossSales = $offerOrders->sum('subtotal');
        $discountGiven = $offerOrders->sum('coupon_discount_amount') + $offerOrders->sum('vendor_discount_amount');
        $effDiscount = $grossSales > 0 ? ($discountGiven / $grossSales) * 100 : 0;

        $this->upsertMetric($restaurant, $date, 'Offers', 'Orders with offers', $ordersWithOffers);
        $this->upsertMetric($restaurant, $date, 'Offers', 'Gross sales from offers (Rs)', $grossSales);
        $this->upsertMetric($restaurant, $date, 'Offers', 'Discount given (Rs)', $discountGiven);
        $this->upsertMetric($restaurant, $date, 'Offers', 'Effective discount (%)', round($effDiscount, 2));
    }

    private function aggregateItemMetrics(Restaurant $restaurant, Carbon $date): void
    {
        $itemData = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.restaurant_id', $restaurant->id)
            ->whereDate('orders.created_at', $date)
            ->where('orders.status', 'delivered')
            ->select(
                'order_items.menu_item_id',
                DB::raw('SUM(order_items.quantity) as total_qty'),
                DB::raw('SUM(order_items.total_price) as total_revenue')
            )
            ->groupBy('order_items.menu_item_id')
            ->get();

        foreach ($itemData as $item) {
            DailyOutletItemMetric::updateOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'menu_item_id' => $item->menu_item_id,
                    'date' => $date->toDateString(),
                ],
                [
                    'orders' => (int) $item->total_qty,
                    'revenue' => round((float) $item->total_revenue, 2),
                ]
            );
        }
    }
}
