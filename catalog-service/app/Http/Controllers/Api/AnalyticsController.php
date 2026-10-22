<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function dashboard(Request $request)
    {
        $restaurantId = $request->user()->id;

        // Today's stats
        $todayOrders = Order::where('restaurant_id', $restaurantId)
            ->whereDate('created_at', today())
            ->get();

        $todayDelivered = $todayOrders->where('status', 'delivered');
        $todayRevenue = $todayDelivered->sum('subtotal');
        $todayOrderCount = $todayDelivered->count();
        $avgOrderValue = $todayOrderCount > 0 ? round($todayRevenue / $todayOrderCount, 2) : 0;

        // Total orders today (all statuses)
        $totalOrdersToday = $todayOrders->count();
        $cancelledToday = $todayOrders->where('status', 'cancelled')->count();

        // Ratings
        $avgRating = RestaurantAnalytics::where('restaurant_id', $restaurantId)
            ->where('date', today())
            ->value('avg_rating') ?? 4.5;

        // Top items today
        $topItems = OrderItem::select('menu_item_id', DB::raw('COUNT(*) as order_count'), DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(total_price) as total_revenue'))
            ->whereIn('order_id', $todayOrders->pluck('id'))
            ->groupBy('menu_item_id')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                $menuItem = MenuItem::find($item->menu_item_id);

                return [
                    'id' => $item->menu_item_id,
                    'name' => $menuItem->name ?? 'Unknown',
                    'order_count' => (int) $item->order_count,
                    'quantity' => (int) $item->total_qty,
                    'revenue' => (float) $item->total_revenue,
                ];
            });

        // Hourly breakdown for today
        $hourlyBreakdown = Order::where('restaurant_id', $restaurantId)
            ->whereDate('created_at', today())
            ->where('status', 'delivered')
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as orders, SUM(subtotal) as revenue')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->map(function ($item) {
                return [
                    'hour' => sprintf('%02d:00', $item->hour),
                    'orders' => (int) $item->orders,
                    'revenue' => (float) ($item->revenue ?? 0),
                ];
            });

        // Daily breakdown (last 7 days)
        $dailyTrend = Order::where('restaurant_id', $restaurantId)
            ->where('status', 'delivered')
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(subtotal) as revenue')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        // This week/month comparison
        $thisWeekRevenue = Order::where('restaurant_id', $restaurantId)
            ->where('status', 'delivered')
            ->where('created_at', '>=', now()->startOfWeek())
            ->sum('subtotal');

        $lastWeekRevenue = Order::where('restaurant_id', $restaurantId)
            ->where('status', 'delivered')
            ->where('created_at', '>=', now()->subWeek()->startOfWeek())
            ->where('created_at', '<', now()->startOfWeek())
            ->sum('subtotal');

        $thisMonthRevenue = Order::where('restaurant_id', $restaurantId)
            ->where('status', 'delivered')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('subtotal');

        $lastMonthRevenue = Order::where('restaurant_id', $restaurantId)
            ->where('status', 'delivered')
            ->where('created_at', '>=', now()->subMonth()->startOfMonth())
            ->where('created_at', '<', now()->startOfMonth())
            ->sum('subtotal');

        $weekChange = $lastWeekRevenue > 0 ? round((($thisWeekRevenue - $lastWeekRevenue) / $lastWeekRevenue) * 100, 1) : 0;
        $monthChange = $lastMonthRevenue > 0 ? round((($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1) : 0;

        // Customer Retention (All time)
        $totalUniqueCustomers = Order::where('restaurant_id', $restaurantId)->distinct('user_id')->count('user_id');
        $returningCustomers = Order::where('restaurant_id', $restaurantId)
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(id) > 1')
            ->get()
            ->count();
        $retentionRate = $totalUniqueCustomers > 0 ? round(($returningCustomers / $totalUniqueCustomers) * 100, 1) : 0;

        // Cancellation Metrics (Last 30 Days)
        $recentOrders = Order::where('restaurant_id', $restaurantId)
            ->where('created_at', '>=', now()->subDays(30))
            ->get();

        $totalRecent = $recentOrders->count();
        $cancelledRecent = $recentOrders->where('status', 'cancelled')->count();
        $cancellationRate = $totalRecent > 0 ? round(($cancelledRecent / $totalRecent) * 100, 1) : 0;

        return response()->json([
            'today' => [
                'revenue' => (float) $todayRevenue,
                'orders' => $todayOrderCount,
                'total_orders' => $totalOrdersToday,
                'cancelled' => $cancelledToday,
                'avg_order_value' => $avgOrderValue,
                'avg_rating' => (float) $avgRating,
            ],
            'this_week' => (float) $thisWeekRevenue,
            'this_month' => (float) $thisMonthRevenue,
            'week_change' => $weekChange,
            'month_change' => $monthChange,
            'top_items' => $topItems,
            'hourly_breakdown' => $hourlyBreakdown,
            'daily_trend' => $dailyTrend,
            'retention' => [
                'total_customers' => $totalUniqueCustomers,
                'returning_customers' => $returningCustomers,
                'rate' => $retentionRate,
            ],
            'cancellations_30d' => [
                'total' => $totalRecent,
                'cancelled' => $cancelledRecent,
                'rate' => $cancellationRate,
            ],
        ]);
    }

    public function trends(Request $request, $restaurantId)
    {
        $days = $request->get('days', 30);
        $data = Order::where('restaurant_id', $restaurantId)
            ->where('status', 'delivered')
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(subtotal) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json(['trends' => $data]);
    }
}
