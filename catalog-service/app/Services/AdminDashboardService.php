<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Rider;
use App\Models\User;

class AdminDashboardService
{
    public function getLiveStats(): array
    {
        $today = today();

        $ordersToday = Order::whereDate('created_at', $today);
        $totalOrdersToday = $ordersToday->count();
        $revenueToday = (float) $ordersToday->where('payment_status', 'paid')->sum('grand_total');
        $activeOrders = Order::whereIn('status', ['confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery'])->count();
        $deliveredToday = Order::whereDate('created_at', $today)->where('status', 'delivered')->count();
        $cancelledToday = Order::whereDate('created_at', $today)->where('status', 'cancelled')->count();

        $onlineRiders = Rider::where('is_online', true)->count();
        $totalRiders = Rider::count();
        $verifiedRiders = Rider::where('is_verified', true)->count();

        $activeRestaurants = Restaurant::where('is_active', true)->count();
        $acceptingRestaurants = Restaurant::where('is_accepting_orders', true)->count();
        $totalRestaurants = Restaurant::count();

        $totalCustomers = User::count();
        $newCustomersToday = User::whereDate('created_at', $today)->count();

        $avgOrderValue = $totalOrdersToday > 0
            ? round($ordersToday->sum('grand_total') / $totalOrdersToday, 2)
            : 0;

        $recentOrders = Order::with(['restaurant:id,name', 'user:id,name', 'rider:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'restaurant' => $o->restaurant?->name,
                'customer' => $o->user?->name,
                'rider' => $o->rider?->name,
                'status' => $o->status,
                'total' => $o->grand_total,
                'payment_mode' => $o->payment_mode,
                'created_at' => $o->created_at->diffForHumans(),
            ]);

        return [
            'timestamp' => now()->toISOString(),
            'orders' => [
                'today' => $totalOrdersToday,
                'active' => $activeOrders,
                'delivered' => $deliveredToday,
                'cancelled' => $cancelledToday,
                'avg_value' => $avgOrderValue,
            ],
            'revenue' => [
                'today' => $revenueToday,
            ],
            'riders' => [
                'online' => $onlineRiders,
                'total' => $totalRiders,
                'verified' => $verifiedRiders,
            ],
            'restaurants' => [
                'active' => $activeRestaurants,
                'accepting' => $acceptingRestaurants,
                'total' => $totalRestaurants,
            ],
            'customers' => [
                'total' => $totalCustomers,
                'new_today' => $newCustomersToday,
            ],
            'recent_orders' => $recentOrders,
        ];
    }
}
