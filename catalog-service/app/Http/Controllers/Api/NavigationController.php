<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\NavigationService;
use Illuminate\Http\Request;

class NavigationController extends Controller
{
    protected NavigationService $navService;

    public function __construct(NavigationService $navService)
    {
        $this->navService = $navService;
    }

    public function toRestaurant(Request $request, Order $order)
    {
        $rider = $request->user();
        if ($order->rider_id !== $rider->id) {
            return response()->json(['error' => 'Not your order.'], 403);
        }

        $order->load('restaurant');
        if (! $order->restaurant?->latitude || ! $order->restaurant?->longitude) {
            return response()->json(['error' => 'Restaurant location not available.'], 422);
        }

        $route = $this->navService->getStepByStep(
            $rider->latitude ?? 28.7942,
            $rider->longitude ?? 76.1384,
            $order->restaurant->latitude,
            $order->restaurant->longitude
        );

        return response()->json([
            'type' => 'to_restaurant',
            'restaurant' => $order->restaurant->name,
            'route' => $route,
            'navigation_url' => $route['navigation_url'],
        ]);
    }

    public function toCustomer(Request $request, Order $order)
    {
        $rider = $request->user();
        if ($order->rider_id !== $rider->id) {
            return response()->json(['error' => 'Not your order.'], 403);
        }

        $order->load('address');
        if (! $order->address?->latitude || ! $order->address?->longitude) {
            return response()->json(['error' => 'Customer location not available.'], 422);
        }

        $route = $this->navService->getStepByStep(
            $rider->latitude ?? $order->restaurant?->latitude ?? 28.7942,
            $rider->longitude ?? $order->restaurant?->longitude ?? 76.1384,
            $order->address->latitude,
            $order->address->longitude
        );

        return response()->json([
            'type' => 'to_customer',
            'customer_address' => $order->address->flat_building.', '.$order->address->street,
            'route' => $route,
            'navigation_url' => $route['navigation_url'],
        ]);
    }
}
