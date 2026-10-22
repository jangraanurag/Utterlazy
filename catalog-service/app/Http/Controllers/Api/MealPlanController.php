<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MealPlan;
use App\Models\MealPlanDelivery;
use App\Models\MealPlanSubscription;
use Illuminate\Http\Request;

class MealPlanController extends Controller
{
    public function index(Request $request)
    {
        $lat = $request->input('lat');
        $lng = $request->input('lng');

        $query = MealPlan::where('is_active', true)->with('restaurant:id,name,logo_url,latitude,longitude');

        if ($lat && $lng) {
            $radius = 0.1;
            $query->whereHas('restaurant', fn ($q) => $q->whereBetween('latitude', [$lat - $radius, $lat + $radius])
                ->whereBetween('longitude', [$lng - $radius, $lng + $radius]));
        }

        return response()->json(['meal_plans' => $query->get()]);
    }

    public function show(MealPlan $mealPlan)
    {
        $mealPlan->load('restaurant:id,name,logo_url,description');

        return response()->json(['meal_plan' => $mealPlan]);
    }

    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'meal_plan_id' => 'required|exists:meal_plans,id',
            'address_id' => 'required|exists:addresses,id',
            'duration_days' => 'required|integer|min:1|max:90',
            'meal_time' => 'required|in:lunch,dinner,lunch_dinner',
            'dietary_notes' => 'sometimes|string|max:500',
            'start_date' => 'required|date|after_or_equal:today',
        ]);

        $mealPlan = MealPlan::findOrFail($validated['meal_plan_id']);
        $user = $request->user();

        $pricePerDay = $mealPlan->price_per_day;
        $duration = $validated['duration_days'];
        if ($duration >= 30 && $mealPlan->price_per_month) {
            $pricePerDay = $mealPlan->price_per_month / 30;
        } elseif ($duration >= 7 && $mealPlan->price_per_week) {
            $pricePerDay = $mealPlan->price_per_week / 7;
        }

        $totalAmount = round($pricePerDay * $duration, 2);
        $startDate = $validated['start_date'];
        $endDate = date('Y-m-d', strtotime($startDate." +{$duration} days"));

        $subscription = MealPlanSubscription::create([
            'user_id' => $user->id,
            'meal_plan_id' => $mealPlan->id,
            'restaurant_id' => $mealPlan->restaurant_id,
            'address_id' => $validated['address_id'],
            'start_date' => $startDate,
            'end_date' => $endDate,
            'meal_time' => $validated['meal_time'],
            'dietary_notes' => $validated['dietary_notes'] ?? null,
            'total_amount' => $totalAmount,
            'payment_status' => 'pending',
            'status' => 'active',
        ]);

        // Generate delivery slots
        $mealTimes = $validated['meal_time'] === 'lunch_dinner' ? ['lunch', 'dinner'] : [$validated['meal_time']];
        for ($i = 0; $i < $duration; $i++) {
            $date = date('Y-m-d', strtotime($startDate." +{$i} days"));
            foreach ($mealTimes as $time) {
                MealPlanDelivery::create([
                    'subscription_id' => $subscription->id,
                    'scheduled_date' => $date,
                    'meal_time' => $time,
                    'status' => 'pending',
                ]);
            }
        }

        return response()->json([
            'message' => "Meal plan subscribed! Starting {$startDate} for {$duration} days. Total: ₹{$totalAmount}",
            'subscription' => $subscription->load(['mealPlan', 'deliveries']),
        ], 201);
    }

    public function mySubscriptions(Request $request)
    {
        $subscriptions = MealPlanSubscription::where('user_id', $request->user()->id)
            ->with(['mealPlan', 'restaurant:id,name,logo_url', 'address', 'deliveries' => fn ($q) => $q->whereDate('scheduled_date', '>=', today()->subDays(1))])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['subscriptions' => $subscriptions]);
    }

    public function pause(Request $request, MealPlanSubscription $subscription)
    {
        if ($subscription->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }
        if ($subscription->status !== 'active') {
            return response()->json(['error' => 'Subscription is not active.'], 422);
        }

        $subscription->update(['status' => 'paused']);

        return response()->json(['message' => 'Subscription paused. Resume anytime.', 'subscription' => $subscription]);
    }

    public function resume(Request $request, MealPlanSubscription $subscription)
    {
        if ($subscription->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }
        if ($subscription->status !== 'paused') {
            return response()->json(['error' => 'Subscription is not paused.'], 422);
        }

        $subscription->update(['status' => 'active']);

        return response()->json(['message' => 'Subscription resumed!', 'subscription' => $subscription]);
    }

    public function cancel(Request $request, MealPlanSubscription $subscription)
    {
        if ($subscription->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        MealPlanDelivery::where('subscription_id', $subscription->id)
            ->where('status', 'pending')
            ->update(['status' => 'missed']);

        return response()->json(['message' => 'Subscription cancelled.', 'subscription' => $subscription]);
    }

    public function skipDay(Request $request, MealPlanSubscription $subscription)
    {
        if ($subscription->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'meal_time' => 'sometimes|in:lunch,dinner',
        ]);

        $query = MealPlanDelivery::where('subscription_id', $subscription->id)
            ->whereDate('scheduled_date', $validated['date']);
        if (! empty($validated['meal_time'])) {
            $query->where('meal_time', $validated['meal_time']);
        }
        $query->update(['status' => 'skipped']);

        return response()->json(['message' => 'Day skipped.']);
    }

    // ─── Vendor endpoints ───

    public function vendorPlans(Request $request)
    {
        $restaurant = $request->user();
        $plans = MealPlan::where('restaurant_id', $restaurant->id)->get();
        $activeSubscriptions = MealPlanSubscription::where('restaurant_id', $restaurant->id)
            ->where('status', 'active')
            ->with('user:id,name,phone')
            ->get();

        return response()->json(['meal_plans' => $plans, 'active_subscriptions' => $activeSubscriptions]);
    }

    public function createPlan(Request $request)
    {
        $restaurant = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'sometimes|string',
            'price_per_day' => 'required|numeric|min:1',
            'price_per_week' => 'sometimes|numeric|min:1',
            'price_per_month' => 'sometimes|numeric|min:1',
            'meals_per_day' => 'sometimes|integer|min:1|max:4',
            'items' => 'sometimes|array',
            'cuisine_type' => 'sometimes|string',
            'is_veg' => 'sometimes|boolean',
        ]);

        $plan = MealPlan::create([
            'restaurant_id' => $restaurant->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price_per_day' => $validated['price_per_day'],
            'price_per_week' => $validated['price_per_week'] ?? null,
            'price_per_month' => $validated['price_per_month'] ?? null,
            'meals_per_day' => $validated['meals_per_day'] ?? 2,
            'items' => $validated['items'] ?? null,
            'cuisine_type' => $validated['cuisine_type'] ?? null,
            'is_veg' => $validated['is_veg'] ?? false,
        ]);

        return response()->json(['message' => 'Meal plan created!', 'meal_plan' => $plan], 201);
    }

    public function updatePlan(Request $request, MealPlan $mealPlan)
    {
        $restaurant = $request->user();
        if ($mealPlan->restaurant_id !== $restaurant->id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $mealPlan->update($request->only([
            'name', 'description', 'price_per_day', 'price_per_week', 'price_per_month',
            'meals_per_day', 'items', 'cuisine_type', 'is_veg', 'is_active',
        ]));

        return response()->json(['message' => 'Meal plan updated.', 'meal_plan' => $mealPlan]);
    }
}
