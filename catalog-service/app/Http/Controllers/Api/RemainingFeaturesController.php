<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompetitorPrice;
use App\Models\CustomerInsight;
use App\Models\DemandEvent;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PickupSlot;
use App\Models\PriceDropAlert;
use App\Models\QrTable;
use App\Models\SpinReward;
use Illuminate\Http\Request;

class RemainingFeaturesController extends Controller
{
    // ─── Dietary Filters ───
    public function filterByDiet(Request $request)
    {
        $tags = $request->input('tags', []);
        if (is_string($tags)) {
            $tags = explode(',', $tags);
        }

        $query = MenuItem::where('is_available', true);

        foreach ($tags as $tag) {
            $query->whereJsonContains('dietary_tags', trim($tag));
        }

        return response()->json(['items' => $query->with('restaurant:id,name')->limit(50)->get()]);
    }

    // ─── Price Drop Alerts ───
    public function setPriceAlert(Request $request)
    {
        $v = $request->validate([
            'menu_item_id' => 'required|exists:menu_items,id',
            'target_price' => 'required|numeric|min:1',
        ]);

        $alert = PriceDropAlert::updateOrCreate(
            ['user_id' => $request->user()->id, 'menu_item_id' => $v['menu_item_id']],
            ['target_price' => $v['target_price'], 'status' => 'active']
        );

        return response()->json(['message' => 'Alert set! We\'ll notify you when the price drops.', 'alert' => $alert]);
    }

    public function myAlerts(Request $request)
    {
        return response()->json([
            'alerts' => PriceDropAlert::where('user_id', $request->user()->id)
                ->where('status', 'active')
                ->with('menuItem:id,name,price,image_url')
                ->get(),
        ]);
    }

    public function removeAlert(Request $request, PriceDropAlert $alert)
    {
        if ($alert->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }
        $alert->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Alert removed.']);
    }

    // ─── Spin the Wheel ───
    public function spin(Request $request)
    {
        $user = $request->user();
        $todaySpins = SpinReward::where('user_id', $user->id)
            ->whereDate('created_at', today())->count();

        if ($todaySpins >= 1) {
            return response()->json(['error' => 'Already spun today! Come back tomorrow.', 'next_spin' => now()->addDay()->toISOString()], 422);
        }

        $rewards = [
            ['reward_type' => 'discount', 'reward_value' => '10% OFF', 'discount_percent' => 10, 'weight' => 30],
            ['reward_type' => 'discount', 'reward_value' => '20% OFF', 'discount_percent' => 20, 'weight' => 15],
            ['reward_type' => 'discount', 'reward_value' => '50% OFF', 'discount_percent' => 50, 'weight' => 5],
            ['reward_type' => 'free_delivery', 'reward_value' => 'Free Delivery', 'discount_amount' => 40, 'weight' => 25],
            ['reward_type' => 'coins', 'reward_value' => '50 Coins', 'weight' => 15],
            ['reward_type' => 'discount', 'reward_value' => '5% OFF', 'discount_percent' => 5, 'weight' => 10],
        ];

        $totalWeight = array_sum(array_column($rewards, 'weight'));
        $rand = rand(1, $totalWeight);
        $cumulative = 0;
        $selected = $rewards[0];

        foreach ($rewards as $reward) {
            $cumulative += $reward['weight'];
            if ($rand <= $cumulative) {
                $selected = $reward;
                break;
            }
        }

        $code = 'SPIN'.strtoupper(substr(md5(uniqid()), 0, 6));

        $reward = SpinReward::create([
            'user_id' => $user->id,
            'reward_type' => $selected['reward_type'],
            'reward_value' => $selected['reward_value'],
            'discount_percent' => $selected['discount_percent'] ?? null,
            'discount_amount' => $selected['discount_amount'] ?? null,
            'coupon_code' => $code,
            'expires_at' => now()->addDays(7),
        ]);

        if ($selected['reward_type'] === 'coins') {
            $user->increment('khaalo_coins', 50);
        }

        return response()->json([
            'message' => "🎉 You won: {$selected['reward_value']}!",
            'reward' => $reward,
            'code' => $code,
            'next_spin' => now()->addDay()->toISOString(),
        ]);
    }

    // ─── Calorie / Nutrition Info ───
    public function nutritionInfo(MenuItem $menuItem)
    {
        return response()->json([
            'item' => $menuItem->only(['id', 'name', 'dietary_tags', 'calories', 'nutrition_info']),
        ]);
    }

    // ─── QR Table Ordering ───
    public function generateQr(Request $request)
    {
        $restaurant = $request->user();
        $v = $request->validate(['table_number' => 'required|string']);

        $table = QrTable::firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'table_number' => $v['table_number']],
            ['qr_code_url' => url("/menu/{$restaurant->id}?table={$v['table_number']}")]
        );

        return response()->json(['table' => $table, 'qr_url' => $table->qr_code_url]);
    }

    // ─── Self Pickup ───
    public function createPickup(Request $request)
    {
        $v = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'pickup_by' => 'required|date|after:now',
        ]);

        $order = Order::findOrFail($v['order_id']);
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Not your order.'], 403);
        }

        $slot = PickupSlot::create([
            'order_id' => $order->id,
            'restaurant_id' => $order->restaurant_id,
            'user_id' => $request->user()->id,
            'pickup_by' => $v['pickup_by'],
        ]);

        return response()->json(['message' => 'Pickup scheduled!', 'slot' => $slot]);
    }

    // ─── Customer Insights (Vendor) ───
    public function customerInsights(Request $request)
    {
        $restaurant = $request->user();
        $insights = CustomerInsight::where('restaurant_id', $restaurant->id)
            ->with('user:id,name,phone')
            ->orderBy('order_count', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'insights' => $insights,
            'total_customers' => $insights->count(),
            'avg_orders_per_customer' => round($insights->avg('order_count') ?? 0, 1),
            'top_customer' => $insights->first()?->user?->name ?? 'N/A',
        ]);
    }

    // ─── Competitor Price View (Vendor) ───
    public function competitorPrices(Request $request)
    {
        $restaurant = $request->user();
        $prices = CompetitorPrice::where('restaurant_id', $restaurant->id)
            ->with('menuItem:id,name,price')
            ->get()
            ->map(fn ($p) => [
                'item_name' => $p->menuItem?->name,
                'your_price' => $p->menuItem?->price,
                'competitor_price' => $p->competitor_price,
                'competitor_name' => $p->competitor_name,
                'difference' => round(($p->menuItem?->price ?? 0) - $p->competitor_price, 2),
            ]);

        return response()->json(['prices' => $prices]);
    }

    // ─── Demand Heat Map (Rider) ───
    public function demandHeatMap(Request $request)
    {
        $events = DemandEvent::where('period', 'hourly')
            ->where('recorded_at', '>=', now()->subHours(2))
            ->orderBy('order_count', 'desc')
            ->limit(30)
            ->get();

        return response()->json(['hotspots' => $events]);
    }

    // ─── Smart Earnings Estimator (Rider) ───
    public function earningsEstimate(Request $request)
    {
        $rider = $request->user();
        $hour = now()->hour;
        $isPeak = ($hour >= 12 && $hour <= 14) || ($hour >= 19 && $hour <= 22);

        // Estimate based on rider's history + current conditions
        $avgPerDelivery = $rider->total_deliveries > 0
            ? round($rider->total_earnings / $rider->total_deliveries, 2)
            : 45;

        $estimatedDeliveries = $isPeak ? 4 : 2;
        $estimatedEarnings = $avgPerDelivery * $estimatedDeliveries;
        $tipEstimate = $estimatedEarnings * 0.1;
        $bonusEstimate = $isPeak ? 40 : 0;

        return response()->json([
            'estimated_earnings' => round($estimatedEarnings + $tipEstimate + $bonusEstimate, 2),
            'estimated_deliveries' => $estimatedDeliveries,
            'avg_per_delivery' => $avgPerDelivery,
            'peak_bonus_estimate' => $bonusEstimate,
            'peak_hours' => $isPeak,
            'period' => $isPeak ? 'Peak hours (higher demand)' : 'Standard hours',
            'message' => $isPeak
                ? "It's peak time! You could earn ~₹".round($estimatedEarnings + $tipEstimate + $bonusEstimate).' in the next 3 hours.'
                : 'Estimated earnings of ~₹'.round($estimatedEarnings + $tipEstimate).' for the next 3 hours.',
        ]);
    }
}
