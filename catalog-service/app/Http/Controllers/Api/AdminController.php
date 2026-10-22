<?php

namespace App\Http\Controllers\Api;

use App\Events\AdminStatsUpdated;
use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\City;
use App\Models\Complaint;
use App\Models\Coupon;
use App\Models\GlobalCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Review;
use App\Models\Rider;
use App\Models\RiderAssignment;
use App\Models\RiderDocument;
use App\Models\RiderPayout;
use App\Models\RiderPayoutCycle;
use App\Models\SystemConfig;
use App\Models\User;
use App\Services\AdminDashboardService;
use App\Services\AuditLogService;
use App\Services\RiderAssignmentService;
use App\Services\RiderPayoutService;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function getConfigs()
    {
        return response()->json(SystemConfig::all()->keyBy('config_key'));
    }

    public function updateConfigs(Request $request)
    {
        $validated = $request->validate([
            'configs' => 'required|array',
            'configs.*.config_key' => 'required|string',
            'configs.*.config_value' => 'nullable',
            'configs.*.type' => 'nullable|string',
        ]);

        foreach ($validated['configs'] as $config) {
            SystemConfig::updateOrCreate(
                ['config_key' => $config['config_key']],
                [
                    'config_value' => $config['config_value'] ?? null,
                    'type' => $config['type'] ?? 'string',
                ]
            );
        }

        return response()->json(['message' => 'Configurations updated successfully']);
    }

    public function getRescueOrders()
    {
        $orders = Order::with(['restaurant', 'items.menuItem'])
            ->where('status', 'abandoned')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($orders);
    }

    /**
     * List all orders with optional status filter
     */
    public function getAllOrders(Request $request)
    {
        $query = Order::with(['restaurant', 'items', 'user:id,name,email,phone'])
            ->orderBy('created_at', 'desc');

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $orders = $query->paginate(20);

        return response()->json($orders);
    }

    /**
     * Dashboard stats
     */
    public function getStats()
    {
        $today = now()->startOfDay();
        $weekStart = now()->startOfWeek();
        $monthStart = now()->startOfMonth();
        $totalOrders = Order::count();

        // Funnel analytics: orders at each stage
        $statusBreakdown = Order::selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');
        $funnel = [];
        $stages = ['pending', 'confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery', 'delivered'];
        foreach ($stages as $stage) {
            $count = (int) ($statusBreakdown[$stage] ?? 0);
            $funnel[$stage] = [
                'count' => $count,
                'pct' => $totalOrders > 0 ? round($count / $totalOrders * 100, 1) : 0,
            ];
        }

        // Service quality (using created_at as baseline for delivered orders)
        $avgFulfillmentTime = Order::where('status', 'delivered')->whereNotNull('delivered_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, delivered_at)) as avg_minutes')
            ->value('avg_minutes');

        $avgRating = Review::avg('rating');

        return response()->json([
            'total_orders' => $totalOrders,
            'today_orders' => Order::where('created_at', '>=', $today)->count(),
            'pending_orders' => (int) ($statusBreakdown['pending'] ?? 0),
            'active_orders' => (int) (($statusBreakdown['confirmed'] ?? 0) + ($statusBreakdown['preparing'] ?? 0) + ($statusBreakdown['ready_for_pickup'] ?? 0) + ($statusBreakdown['out_for_delivery'] ?? 0)),
            'delivered_today' => Order::where('status', 'delivered')->where('updated_at', '>=', $today)->count(),
            'today_revenue' => Order::where('status', 'delivered')->where('updated_at', '>=', $today)->sum('grand_total'),
            'week_revenue' => Order::where('status', 'delivered')->where('updated_at', '>=', $weekStart)->sum('grand_total'),
            'month_revenue' => Order::where('status', 'delivered')->where('updated_at', '>=', $monthStart)->sum('grand_total'),
            'total_revenue' => Order::where('status', 'delivered')->sum('grand_total'),
            'status_breakdown' => $statusBreakdown,
            // Funnel analytics
            'funnel' => $funnel,
            // Service quality
            'avg_prep_time' => round($avgPrepTime ?? 0, 1),
            'avg_delivery_time' => round($avgDeliveryTime ?? 0, 1),
            'avg_fulfillment_time' => round($avgFulfillmentTime ?? 0, 1),
            'avg_rating' => round($avgRating ?? 0, 2),
            // Rider KPIs
            'total_riders' => Rider::count(),
            'online_riders' => Rider::where('is_online', true)->count(),
            'active_deliveries' => Rider::whereNotNull('current_order_id')->count(),
            'unsettled_payouts' => round(RiderPayout::where('is_settled', false)->sum('total_payout'), 2),
            'avg_delivery_payout' => round(RiderPayout::avg('total_payout') ?? 0, 2),
            'surge_multiplier' => RiderAssignmentService::getSurgeMultiplier(),
        ]);
    }

    /**
     * Admin can force-update any order status
     */
    public function adminUpdateStatus(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,preparing,ready_for_pickup,out_for_delivery,delivered,cancelled,abandoned,rescued',
        ]);

        $order->update(['status' => $request->status]);

        return response()->json(['message' => 'Status updated.', 'order' => $order->fresh()->load('restaurant')]);
    }

    // ─── Coupons CRUD ───

    public function getCoupons()
    {
        return response()->json(Coupon::orderBy('created_at', 'desc')->get());
    }

    public function storeCoupon(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:coupons,code',
            'title' => 'required|string',
            'description' => 'nullable|string',
            'type' => 'required|in:percentage,flat',
            'value' => 'required|numeric|min:0',
            'min_order' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:0',
            'per_user_limit' => 'nullable|integer|min:1',
            'is_active' => 'sometimes|boolean',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
        ]);

        $coupon = Coupon::create($validated);

        return response()->json($coupon, 201);
    }

    public function updateCoupon(Request $request, Coupon $coupon)
    {
        $validated = $request->validate([
            'code' => 'sometimes|string|unique:coupons,code,'.$coupon->id,
            'title' => 'sometimes|string',
            'description' => 'nullable|string',
            'type' => 'sometimes|in:percentage,flat',
            'value' => 'sometimes|numeric|min:0',
            'min_order' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:0',
            'per_user_limit' => 'nullable|integer|min:1',
            'is_active' => 'sometimes|boolean',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date',
        ]);

        $coupon->update($validated);

        return response()->json($coupon->fresh());
    }

    public function deleteCoupon(Coupon $coupon)
    {
        $coupon->delete();

        return response()->json(['message' => 'Coupon deleted.']);
    }

    // ─── Rider Management ───

    /**
     * List all riders with stats.
     */
    /**
     * Deactivate a rider. Reason mandatory, active delivery must be cleared,
     * tokens revoked, audit kept. rider_ops permission required by the route.
     */
    public function deactivateRider(Request $request, Rider $rider)
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:10|max:200',
        ]);

        if ($rider->current_order_id) {
            return response()->json([
                'error' => 'Rider has an active delivery. Reassign it before deactivation.',
            ], 422);
        }

        $rider->update([
            'is_online' => false,
            'is_verified' => false,
            'deactivated_at' => now(),
            'deactivation_reason' => $validated['reason'],
        ]);

        $rider->tokens()->delete();

        AuditLogService::log('deactivate_rider', 'riders', 'rider', $rider->id, null, [
            'reason' => $validated['reason'],
        ]);

        return response()->json(['message' => 'Rider deactivated. Audit history preserved.']);
    }

    /**
     * Food-waste claim: platform cancelled after the kitchen prepared food.
     * Originates in the finance approval engine; nothing is paid here.
     */
    public function createFoodWasteClaim(Request $request, Order $order)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'reason' => 'required|string|min:10|max:300',
            'photo_path' => 'sometimes|string|max:255',
        ]);

        if (! in_array($order->cancel_reason, ['no_rider', 'customer_unreachable'], true)) {
            return response()->json([
                'error' => 'Food-waste claims apply to platform-side cancellations (no_rider / customer_unreachable).',
            ], 422);
        }

        if ((float) $validated['amount'] > (float) $order->subtotal) {
            return response()->json([
                'error' => 'Claim amount cannot exceed the food subtotal.',
                'max_amount' => (float) $order->subtotal,
            ], 422);
        }

        $claim = \App\Models\FoodWasteClaim::create([
            'order_id' => $order->id,
            'restaurant_id' => $order->restaurant_id,
            'amount' => $validated['amount'],
            'reason' => $validated['reason'],
            'photo_path' => $validated['photo_path'] ?? $order->delivery_photo_path,
            'status' => \App\Models\FoodWasteClaim::STATUS_DRAFT,
        ]);

        $instance = \App\Services\FinanceApprovalBridge::originateFoodWasteClaim($claim, $request->user());

        return response()->json([
            'message' => 'Food-waste claim submitted for approval.',
            'claim' => $claim->fresh(),
            'workflow_id' => $instance->id,
        ], 201);
    }

    public function getRiders(Request $request)
    {
        $query = Rider::orderBy('created_at', 'desc');

        if ($request->has('status')) {
            if ($request->status === 'online') {
                $query->where('is_online', true);
            }
            if ($request->status === 'offline') {
                $query->where('is_online', false);
            }
            if ($request->status === 'unverified') {
                $query->where('is_verified', false);
            }
        }

        $riders = $query->paginate(20);

        return response()->json($riders);
    }

    /**
     * Toggle rider verification status.
     */
    public function verifyRider(Request $request, Rider $rider)
    {
        $oldStatus = $rider->is_verified;
        $rider->update(['is_verified' => ! $rider->is_verified]);
        AuditLogService::log('verify_rider', 'riders', 'rider', $rider->id, ['is_verified' => $oldStatus], ['is_verified' => $rider->is_verified]);

        return response()->json([
            'message' => $rider->is_verified ? 'Rider verified.' : 'Rider unverified.',
            'rider' => $rider->fresh(),
        ]);
    }

    public function getRiderDocuments(Request $request, Rider $rider)
    {
        return response()->json([
            'rider' => $rider->only(['id', 'name', 'phone', 'vehicle_type', 'vehicle_number', 'is_verified', 'verification_status']),
            'documents' => $rider->documents()->get(),
        ]);
    }

    public function approveRiderDocument(Request $request, Rider $rider, RiderDocument $document)
    {
        if ($document->rider_id !== $rider->id) {
            return response()->json(['error' => 'Document does not belong to this rider.'], 403);
        }

        $document->update([
            'status' => 'approved',
            'rejection_reason' => null,
            'verified_at' => now(),
            'verified_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Document approved.',
            'document' => $document->fresh(),
        ]);
    }

    public function rejectRiderDocument(Request $request, Rider $rider, RiderDocument $document)
    {
        if ($document->rider_id !== $rider->id) {
            return response()->json(['error' => 'Document does not belong to this rider.'], 403);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $document->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['reason'],
            'verified_at' => now(),
            'verified_by' => $request->user()->id,
        ]);

        $rider->update([
            'verification_status' => 'rejected',
            'verification_notes' => $validated['reason'],
        ]);

        return response()->json([
            'message' => 'Document rejected.',
            'document' => $document->fresh(),
        ]);
    }

    public function verifyRiderDocuments(Request $request, Rider $rider)
    {
        $required = ['aadhaar_front', 'aadhaar_back', 'driving_license_front', 'pan_card', 'selfie'];
        $docs = $rider->documents()->whereIn('document_type', $required)->where('status', 'approved')->count();

        if ($docs < count($required)) {
            return response()->json(['error' => 'Not all required documents are approved.'], 422);
        }

        $rider->update([
            'is_verified' => true,
            'verification_status' => 'verified',
            'verification_notes' => null,
        ]);

        return response()->json([
            'message' => 'Rider fully verified. Can now go online and accept deliveries.',
            'rider' => $rider->fresh(),
        ]);
    }

    /**
     * Admin-create a rider account.
     */
    public function createRider(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:riders,email',
            'phone' => 'required|string|unique:riders,phone',
            'password' => 'required|string|min:6',
            'vehicle_type' => 'sometimes|in:bicycle,scooter,bike,car',
            'vehicle_number' => 'sometimes|string',
            'is_verified' => 'sometimes|boolean',
        ]);

        $rider = Rider::create(array_merge($validated, [
            'is_verified' => $validated['is_verified'] ?? true,
        ]));

        return response()->json($rider, 201);
    }

    // ─── Rider Payouts ───

    /**
     * List all payouts with filters.
     */
    public function getRiderPayouts(Request $request)
    {
        $query = RiderPayout::with(['rider:id,name,phone', 'order:id,restaurant_id,grand_total,status'])
            ->orderBy('created_at', 'desc');

        if ($request->has('rider_id')) {
            $query->where('rider_id', $request->rider_id);
        }
        if ($request->has('settled')) {
            $query->where('is_settled', $request->settled === 'true');
        }
        if ($request->has('date')) {
            $query->whereDate('created_at', $request->date);
        }

        return response()->json([
            'payouts' => $query->paginate(20),
            'summary' => RiderPayoutService::getSummary(),
        ]);
    }

    /**
     * Settle payouts (bulk or per-rider).
     */
    public function settlePayouts(Request $request)
    {
        if ($request->has('rider_id')) {
            $count = RiderPayoutService::settleForRider($request->rider_id);
        } else {
            $count = RiderPayoutService::settleAll();
        }

        return response()->json([
            'message' => "{$count} payouts settled.",
            'settled_count' => $count,
        ]);
    }

    /**
     * Assignment logs for debugging.
     */
    public function getAssignmentLogs(Request $request)
    {
        $query = RiderAssignment::with(['rider:id,name,phone', 'order:id,restaurant_id,status'])
            ->orderBy('created_at', 'desc');

        if ($request->has('order_id')) {
            $query->where('order_id', $request->order_id);
        }
        if ($request->has('rider_id')) {
            $query->where('rider_id', $request->rider_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->paginate(30));
    }

    // ─── Global Categories CRUD ───

    public function getGlobalCategories()
    {
        return response()->json(GlobalCategory::orderBy('sort_order')->get());
    }

    public function storeGlobalCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:global_categories,name',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);

        $category = GlobalCategory::create($validated);

        return response()->json($category, 201);
    }

    public function updateGlobalCategory(Request $request, $id)
    {
        $category = GlobalCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|unique:global_categories,name,'.$category->id,
            'description' => 'nullable|string',
            'image_url' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);

        $category->update($validated);

        return response()->json($category->fresh());
    }

    public function deleteGlobalCategory($id)
    {
        $category = GlobalCategory::findOrFail($id);
        // Set mapped menu items global_category_id to null
        MenuItem::where('global_category_id', $category->id)->update(['global_category_id' => null]);
        $category->delete();

        return response()->json(['message' => 'Global category deleted successfully']);
    }

    // ─── Admin Menu Item Mapping ───

    public function getMenuItems()
    {
        // Return all menu items with their restaurant details and category details
        $items = MenuItem::with(['restaurant:id,name', 'globalCategory:id,name'])
            ->orderBy('name')
            ->get();

        return response()->json($items);
    }

    public function updateMenuItem(Request $request, $id)
    {
        $item = MenuItem::findOrFail($id);

        $validated = $request->validate([
            'global_category_id' => 'nullable|uuid|exists:global_categories,id',
            'price' => 'sometimes|numeric|min:0',
            'is_available' => 'sometimes|boolean',
        ]);

        $item->update($validated);

        return response()->json($item->fresh()->load(['restaurant:id,name', 'globalCategory:id,name']));
    }

    // ─── Restaurant Management ───

    public function getRestaurants(Request $request)
    {
        $query = Restaurant::orderBy('created_at', 'desc')
            ->withCount(['orders as orders_count' => function ($q) {
                $q->where('status', 'delivered');
            }])
            ->withSum(['orders as revenue' => function ($q) {
                $q->where('status', 'delivered');
            }], 'grand_total')
            ->addSelect(['avg_rating' => Review::selectRaw('COALESCE(AVG(rating), 0)')
                ->whereColumn('restaurant_id', 'restaurants.id'),
            ]);

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate(20));
    }

    public function getRestaurantDetail(Restaurant $restaurant)
    {
        $restaurant->loadCount(['orders as orders_count' => function ($q) {
            $q->where('status', 'delivered');
        }]);

        $restaurant->items_count = MenuItem::where('restaurant_id', $restaurant->id)->count();
        $restaurant->avg_rating = Review::where('restaurant_id', $restaurant->id)->avg('rating') ?? 0;

        $restaurant->recent_orders = Order::where('restaurant_id', $restaurant->id)
            ->where('status', 'delivered')
            ->latest()
            ->take(5)
            ->get();

        return response()->json($restaurant);
    }

    public function updateRestaurant(Request $request, Restaurant $restaurant)
    {
        $validated = $request->validate([
            'is_active' => 'sometimes|boolean',
            'commission_rate' => 'sometimes|numeric|min:0|max:100',
            'name' => 'sometimes|string',
            'phone' => 'sometimes|string',
            'description' => 'sometimes|string',
            'is_visible' => 'sometimes|boolean',
        ]);

        $restaurant->update($validated);

        return response()->json($restaurant->fresh());
    }

    // ─── Review Moderation ───

    public function getReviews(Request $request)
    {
        $query = Review::with(['restaurant:id,name', 'user:id,name,email', 'order:id,order_number'])
            ->orderBy('created_at', 'desc');

        if ($request->has('restaurant_id')) {
            $query->where('restaurant_id', $request->restaurant_id);
        }

        return response()->json($query->paginate(20));
    }

    // ─── Complaint Management ───

    public function getComplaints(Request $request)
    {
        $query = Complaint::with(['order:id,order_number,grand_total', 'user:id,name,email', 'restaurant:id,name'])
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->paginate(20));
    }

    // ─── Customer Management ───

    public function getCustomers(Request $request)
    {
        $query = User::withCount(['orders as total_orders'])
            ->orderBy('created_at', 'desc');

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate(20));
    }

    // ─── City Management ───

    public function getCities()
    {
        return response()->json(City::orderBy('name')->get());
    }

    public function storeCity(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'base_delivery_fee' => 'required|numeric|min:0',
            'base_commission_rate' => 'required|numeric|min:0|max:100',
            'is_active' => 'sometimes|boolean',
            'latitude' => 'sometimes|numeric',
            'longitude' => 'sometimes|numeric',
        ]);

        $city = City::create($validated);

        return response()->json($city, 201);
    }

    public function updateCity(Request $request, City $city)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'state' => 'sometimes|string|max:100',
            'base_delivery_fee' => 'sometimes|numeric|min:0',
            'base_commission_rate' => 'sometimes|numeric|min:0|max:100',
            'is_active' => 'sometimes|boolean',
            'latitude' => 'sometimes|numeric',
            'longitude' => 'sometimes|numeric',
        ]);

        $city->update($validated);

        return response()->json($city->fresh());
    }

    public function deleteCity(City $city)
    {
        $city->delete();

        return response()->json(['message' => 'City deleted.']);
    }

    // ─── Banner Management ───

    public function getBanners()
    {
        return response()->json(Banner::orderBy('sort_order')->get());
    }

    public function storeBanner(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'image_url' => 'required|string|max:500',
            'link_url' => 'nullable|string|max:500',
            'position' => 'required|in:home_top,home_mid,restaurant_page',
            'target_audience' => 'required|in:all,new_users,returning_users',
            'sort_order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $banner = Banner::create($validated);

        return response()->json($banner, 201);
    }

    public function updateBanner(Request $request, Banner $banner)
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'image_url' => 'sometimes|string|max:500',
            'link_url' => 'nullable|string|max:500',
            'position' => 'sometimes|in:home_top,home_mid,restaurant_page',
            'target_audience' => 'sometimes|in:all,new_users,returning_users',
            'sort_order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $banner->update($validated);

        return response()->json($banner->fresh());
    }

    public function deleteBanner(Banner $banner)
    {
        $banner->delete();

        return response()->json(['message' => 'Banner deleted.']);
    }

    // ─── Ranking Monitor ───
    public function rankingMonitor()
    {
        $restaurants = Restaurant::select([
            'id', 'name', 'is_active',
            'ema_consumer_rating', 'ema_rider_rating',
            'beta_alpha', 'beta_beta',
            'batching_score', 'prep_time_variance',
            'total_impressions', 'total_clicks',
            'avg_prep_time_minutes as avg_prep_time',
            'total_orders_completed as total_orders',
        ])->orderBy('name')->get()->toArray();

        return response()->json($restaurants);
    }

    // ─── Ranking Config ───
    public function getRankingConfig()
    {
        $keys = [
            'ranking_w_organic', 'ranking_w_operational', 'ranking_w_rider_feedback',
            'ranking_w_proximity', 'ranking_w_exploration', 'ranking_w_sponsored',
            'ranking_rating_gold_threshold', 'ranking_rating_bad_threshold',
            'ranking_cancel_sigmoid_theta', 'ranking_cancel_sigmoid_k',
            'ranking_ema_high_vol_alpha', 'ranking_ema_new_alpha', 'ranking_ema_default_alpha',
            'ranking_freshness_days', 'ranking_diversity_penalty',
            'ranking_peak_w_operational', 'ranking_peak_w_exploration',
            'ranking_offpeak_w_exploration',
            'ranking_gsp_min_relevance', 'ranking_gsp_slot_positions',
        ];
        $configs = SystemConfig::whereIn('config_key', $keys)->pluck('config_value', 'config_key');

        $defaults = [
            'ranking_w_organic' => '0.35',
            'ranking_w_operational' => '0.20',
            'ranking_w_rider_feedback' => '0.10',
            'ranking_w_proximity' => '0.20',
            'ranking_w_exploration' => '0.05',
            'ranking_w_sponsored' => '0.10',
            'ranking_rating_gold_threshold' => '4.2',
            'ranking_rating_bad_threshold' => '4.0',
            'ranking_cancel_sigmoid_theta' => '0.03',
            'ranking_cancel_sigmoid_k' => '50',
            'ranking_ema_high_vol_alpha' => '0.1',
            'ranking_ema_new_alpha' => '0.5',
            'ranking_ema_default_alpha' => '0.25',
            'ranking_freshness_days' => '14',
            'ranking_diversity_penalty' => '0.85',
            'ranking_peak_w_operational' => '0.25',
            'ranking_peak_w_exploration' => '0.03',
            'ranking_offpeak_w_exploration' => '0.12',
            'ranking_gsp_min_relevance' => '0.5',
            'ranking_gsp_slot_positions' => '2,5,9',
        ];

        foreach ($defaults as $key => $default) {
            if (! isset($configs[$key])) {
                $configs[$key] = $default;
            }
        }

        return response()->json($configs);
    }

    public function saveRankingConfig(Request $request)
    {
        $validKeys = [
            'ranking_w_organic', 'ranking_w_operational', 'ranking_w_rider_feedback',
            'ranking_w_proximity', 'ranking_w_exploration', 'ranking_w_sponsored',
            'ranking_rating_gold_threshold', 'ranking_rating_bad_threshold',
            'ranking_cancel_sigmoid_theta', 'ranking_cancel_sigmoid_k',
            'ranking_ema_high_vol_alpha', 'ranking_ema_new_alpha', 'ranking_ema_default_alpha',
            'ranking_freshness_days', 'ranking_diversity_penalty',
            'ranking_peak_w_operational', 'ranking_peak_w_exploration',
            'ranking_offpeak_w_exploration',
            'ranking_gsp_min_relevance', 'ranking_gsp_slot_positions',
        ];

        foreach ($request->all() as $key => $value) {
            if (in_array($key, $validKeys)) {
                SystemConfig::updateOrCreate(
                    ['config_key' => $key],
                    ['config_value' => (string) $value, 'type' => 'string']
                );
            }
        }

        return response()->json(['message' => 'Ranking config updated.']);
    }

    public function liveStats()
    {
        $service = new AdminDashboardService;
        $stats = $service->getLiveStats();

        return response()->json($stats);
    }

    public function broadcastStats()
    {
        $service = new AdminDashboardService;
        $stats = $service->getLiveStats();

        broadcast(new AdminStatsUpdated($stats));

        return response()->json(['message' => 'Stats broadcasted.', 'stats' => $stats]);
    }

    public function orderMap()
    {
        $activeOrders = Order::whereNotIn('status', ['delivered', 'completed', 'cancelled'])
            ->with([
                'restaurant:id,name,latitude,longitude,address',
                'rider:id,name,phone,latitude,longitude,vehicle_type,vehicle_number',
                'user:id,name',
                'address:id,latitude,longitude,flat_building,street',
            ])
            ->get()
            ->map(fn ($order) => [
                'id' => $order->id,
                'status' => $order->status,
                'restaurant' => $order->restaurant ? [
                    'name' => $order->restaurant->name,
                    'lat' => $order->restaurant->latitude,
                    'lng' => $order->restaurant->longitude,
                    'address' => $order->restaurant->address,
                ] : null,
                'rider' => $order->rider ? [
                    'name' => $order->rider->name,
                    'phone' => $order->rider->phone,
                    'lat' => $order->rider->latitude,
                    'lng' => $order->rider->longitude,
                    'vehicle' => $order->rider->vehicle_type,
                ] : null,
                'customer' => [
                    'name' => $order->user?->name ?? 'Customer',
                    'lat' => $order->address?->latitude,
                    'lng' => $order->address?->longitude,
                    'address' => $order->address ? ($order->address->flat_building.', '.$order->address->street) : null,
                ],
                'total' => $order->grand_total,
                'payment_mode' => $order->payment_mode,
                'created_at' => $order->created_at->diffForHumans(),
                'wait_time_minutes' => $order->status !== 'delivered' ? now()->diffInMinutes($order->created_at) : 0,
            ]);

        $stats = [
            'total_active' => $activeOrders->count(),
            'waiting_pickup' => $activeOrders->whereIn('status', ['pending', 'confirmed', 'preparing', 'ready_for_pickup'])->count(),
            'out_for_delivery' => $activeOrders->where('status', 'out_for_delivery')->count(),
            'unassigned' => $activeOrders->where('status', 'ready_for_pickup')->filter(fn ($o) => ! $o['rider'])->count(),
        ];

        return response()->json([
            'orders' => $activeOrders,
            'stats' => $stats,
        ]);
    }

    public function cloneRestaurantConfig(Request $request)
    {
        $validated = $request->validate([
            'source_restaurant_id' => 'required|exists:restaurants,id',
            'target_city_id' => 'required|exists:cities,id',
            'new_name' => 'required|string|max:255',
            'new_email' => 'required|email|unique:restaurants,email',
            'new_phone' => 'required|string',
            'copy_menu' => 'sometimes|boolean',
        ]);

        $source = Restaurant::with(['menuItems'])->findOrFail($validated['source_restaurant_id']);
        $city = City::findOrFail($validated['target_city_id']);

        $cloned = Restaurant::create([
            'name' => $validated['new_name'],
            'email' => $validated['new_email'],
            'phone' => $validated['new_phone'],
            'password' => bcrypt('welcome123'),
            'description' => $source->description,
            'cuisine_type' => $source->cuisine_type,
            'commission_rate' => $city->base_commission_rate ?? $source->commission_rate,
            'operating_hours' => $source->operating_hours,
            'is_active' => true,
            'is_accepting_orders' => true,
            'city_id' => $city->id,
            'latitude' => $city->latitude ?? $source->latitude,
            'longitude' => $city->longitude ?? $source->longitude,
            'address' => $city->name.', '.$city->state,
        ]);

        if (($validated['copy_menu'] ?? false) && $source->menuItems) {
            foreach ($source->menuItems as $item) {
                $cloned->menuItems()->create($item->replicate()->toArray());
            }
        }

        AuditLogService::log('clone_restaurant', 'restaurants', 'restaurant', $cloned->id,
            null, ['source' => $source->id, 'target_city' => $city->id, 'name' => $cloned->name]);

        return response()->json([
            'message' => "Restaurant '{$cloned->name}' created in {$city->name}. Default password: welcome123",
            'restaurant' => $cloned->load('menuItems'),
        ]);
    }

    public function cityOverview(Request $request, City $city)
    {
        return response()->json([
            'city' => $city,
            'stats' => [
                'restaurants' => Restaurant::where('city_id', $city->id)->count(),
                'active_orders' => Order::whereHas('restaurant', fn ($q) => $q->where('city_id', $city->id))
                    ->whereIn('status', ['confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery'])->count(),
                'total_revenue' => Order::whereHas('restaurant', fn ($q) => $q->where('city_id', $city->id))
                    ->where('status', 'delivered')->sum('grand_total'),
                'riders' => Rider::where('city_id', $city->id)->count(),
            ],
        ]);
    }

    // ─── Rider Payout Cycle Management (§5 maker-checker) ───
    public function getRiderPayoutCycles(Request $request)
    {
        $query = RiderPayoutCycle::with(['rider:id,name,phone', 'enteredBy:id,name', 'approvedBy:id,name'])
            ->orderBy('cycle_date', 'desc');

        if ($request->has('rider_id')) {
            $query->where('rider_id', $request->rider_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('date_from')) {
            $query->where('cycle_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('cycle_date', '<=', $request->date_to);
        }

        return response()->json($query->paginate(20));
    }

    public function payRiderPayoutCycle(Request $request, RiderPayoutCycle $cycle)
    {
        $validated = $request->validate([
            'utr_number' => 'required|string|max:100',
            'payment_mode' => 'sometimes|string|max:50',
            'payment_notes' => 'nullable|string|max:500',
        ]);

        if ($cycle->status === 'paid') {
            return response()->json(['error' => 'This payout cycle is already paid'], 422);
        }

        $admin = $request->user();

        $cycle->update([
            'status' => 'paid',
            'utr_number' => $validated['utr_number'],
            'payment_mode' => $validated['payment_mode'] ?? 'bank_transfer',
            'payment_date' => now()->toDateString(),
            'payment_notes' => $validated['payment_notes'] ?? null,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        // Mark per-order payouts as settled
        RiderPayout::where('rider_id', $cycle->rider_id)
            ->whereDate('created_at', $cycle->cycle_date)
            ->where('is_settled', false)
            ->update([
                'is_settled' => true,
                'settled_at' => now(),
            ]);

        return response()->json([
            'message' => "Payout cycle for {$cycle->cycle_date} marked as paid.",
            'cycle' => $cycle->fresh()->load(['rider:id,name,phone', 'approvedBy:id,name']),
        ]);
    }
}
