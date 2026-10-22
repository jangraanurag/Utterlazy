<?php

namespace App\Http\Controllers\Api;

use App\Events\OrderStatusChanged;
use App\Http\Controllers\Controller;
use App\Jobs\AssignRiderAfterAcceptance;
use App\Models\AddOnGroup;
use App\Models\AddOnItem;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantPackagingSetting;
use App\Models\Rider;
use App\Models\SystemConfig;
use App\Models\VendorPayment;
use App\Services\RiderAssignmentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class VendorController extends Controller
{
    // ═══════════════════════════════════════
    //  AUTH
    // ═══════════════════════════════════════

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $restaurant = Restaurant::where('email', $request->email)->first();

        if (! $restaurant || ! Hash::check($request->password, $restaurant->password)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        if (! $restaurant->is_active) {
            return response()->json(['error' => 'Your restaurant account is deactivated. Contact admin.'], 403);
        }

        $restaurant->update(['last_login_at' => now()]);
        $token = $restaurant->createToken('vendor-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'restaurant' => $restaurant->makeHidden(['password']),
        ]);
    }

    // ═══════════════════════════════════════
    //  PROFILE
    // ═══════════════════════════════════════

    public function me(Request $request)
    {
        $restaurant = $request->user();

        $todayOrders = Order::where('restaurant_id', $restaurant->id)
            ->whereDate('created_at', today());

        $stats = [
            'today_revenue' => round($todayOrders->clone()->where('status', 'delivered')->sum('subtotal'), 2),
            'today_orders' => $todayOrders->clone()->count(),
            'today_completed' => $todayOrders->clone()->where('status', 'delivered')->count(),
            'active_orders' => Order::where('restaurant_id', $restaurant->id)
                ->whereIn('status', ['pending', 'confirmed', 'preparing', 'ready_for_pickup'])
                ->count(),
            'avg_rating' => 4.8, // TODO: Calculate from rider_ratings
            'pending_payout' => round(
                Order::where('restaurant_id', $restaurant->id)
                    ->where('status', 'delivered')
                    ->whereNull('delivered_at')
                    ->sum('subtotal') * (1 - ($restaurant->commission_rate / 100)),
                2
            ),
        ];

        return response()->json([
            'restaurant' => $restaurant->makeHidden(['password']),
            'stats' => $stats,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $restaurant = $request->user();
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'phone' => 'sometimes|string|max:20',
            'logo_url' => 'sometimes|string',
            'address' => 'sometimes|string',
            'latitude' => 'sometimes|numeric',
            'longitude' => 'sometimes|numeric',
            'fssai_number' => 'sometimes|string|max:20',
            'gst_number' => 'sometimes|string|max:20',
            'pan_number' => 'sometimes|string|max:12',
            'bank_account_number' => 'sometimes|string',
            'bank_ifsc' => 'sometimes|string|max:15',
            'bank_name' => 'sometimes|string',
            'bank_holder_name' => 'sometimes|string',
            'auto_accept_orders' => 'sometimes|boolean',
        ]);

        $bankChanged = collect(['bank_account_number', 'bank_ifsc', 'bank_name', 'bank_holder_name'])
            ->contains(fn ($field) => array_key_exists($field, $validated)
                && (string) $restaurant->{$field} !== (string) $validated[$field]);

        $restaurant->update($validated);

        if ($bankChanged) {
            $restaurant->tokens()->delete();

            return response()->json([
                'message' => 'Profile updated. Bank details changed — please log in again.',
                'session_revoked' => true,
                'restaurant' => $restaurant->fresh(),
            ]);
        }

        return response()->json(['message' => 'Profile updated', 'restaurant' => $restaurant->fresh()]);
    }

    public function toggleOnline(Request $request)
    {
        $restaurant = $request->user();

        // Prevent going online without GPS location set
        $wantOnline = ! $restaurant->is_accepting_orders;
        if ($wantOnline && (is_null($restaurant->latitude) || is_null($restaurant->longitude))) {
            return response()->json([
                'is_accepting_orders' => false,
                'message' => 'Set your restaurant location on the map before going online.',
                'error' => 'location_required',
            ], 422);
        }

        $restaurant->update([
            'is_accepting_orders' => $wantOnline,
            'manually_offline' => ! $wantOnline,
            'paused_until' => null,
            'permanent_offline' => false,
        ]);

        return response()->json([
            'is_accepting_orders' => $restaurant->fresh()->is_accepting_orders,
            'manually_offline' => $restaurant->fresh()->manually_offline,
            'paused_until' => $restaurant->fresh()->paused_until,
            'permanent_offline' => $restaurant->fresh()->permanent_offline,
            'message' => $wantOnline ? 'Now accepting orders!' : 'You are now offline.',
        ]);
    }

    public function pauseOrders(Request $request)
    {
        $request->validate([
            'pause_duration' => 'required|integer|in:30,60,120',
        ]);

        $restaurant = $request->user();
        $duration = $request->pause_duration;
        $pausedUntil = now()->addMinutes($duration);

        $restaurant->update([
            'is_accepting_orders' => false,
            'paused_until' => $pausedUntil,
            'manually_offline' => false,
            'permanent_offline' => false,
        ]);

        Cache::put('restaurant_pause_'.$restaurant->id, true, $pausedUntil);

        return response()->json([
            'message' => "Orders paused for {$duration} minutes.",
            'is_accepting_orders' => false,
            'paused_until' => $pausedUntil->toIso8601String(),
            'manually_offline' => false,
            'permanent_offline' => false,
        ]);
    }

    public function resumeOrders(Request $request)
    {
        $restaurant = $request->user();

        if (is_null($restaurant->latitude) || is_null($restaurant->longitude)) {
            return response()->json([
                'is_accepting_orders' => false,
                'message' => 'Set your restaurant location on the map before going online.',
                'error' => 'location_required',
            ], 422);
        }

        $restaurant->update([
            'is_accepting_orders' => true,
            'paused_until' => null,
            'manually_offline' => false,
            'permanent_offline' => false,
        ]);

        Cache::forget('restaurant_pause_'.$restaurant->id);

        return response()->json([
            'message' => 'Orders resumed. You are online!',
            'is_accepting_orders' => true,
            'paused_until' => null,
            'manually_offline' => false,
            'permanent_offline' => false,
        ]);
    }

    public function goOffline(Request $request)
    {
        $request->validate([
            'option' => 'required|in:2_hours,till_day_end,till_manual,custom',
            'custom_datetime' => 'required_if:option,custom|date|after:now',
        ]);

        $restaurant = $request->user();
        $option = $request->option;

        $pausedUntil = null;
        $manuallyOffline = true;
        $permanentOffline = false;

        if ($option === '2_hours') {
            $pausedUntil = now()->addHours(2);
            $manuallyOffline = false;
        } elseif ($option === 'till_day_end') {
            $manuallyOffline = true;
            $permanentOffline = false;
        } elseif ($option === 'till_manual') {
            $manuallyOffline = true;
            $permanentOffline = true;
        } elseif ($option === 'custom') {
            $pausedUntil = Carbon::parse($request->custom_datetime);
            $manuallyOffline = false;
        }

        $restaurant->update([
            'is_accepting_orders' => false,
            'paused_until' => $pausedUntil,
            'manually_offline' => $manuallyOffline,
            'permanent_offline' => $permanentOffline,
        ]);

        if ($pausedUntil) {
            Cache::put('restaurant_pause_'.$restaurant->id, true, $pausedUntil);
        } else {
            Cache::forget('restaurant_pause_'.$restaurant->id);
        }

        return response()->json([
            'message' => 'Restaurant is now offline.',
            'is_accepting_orders' => false,
            'manually_offline' => $manuallyOffline,
            'permanent_offline' => $permanentOffline,
            'paused_until' => $pausedUntil ? $pausedUntil->toIso8601String() : null,
        ]);
    }

    public function updateOperatingHours(Request $request)
    {
        $request->validate(['operating_hours' => 'required|array']);
        $restaurant = $request->user();
        $restaurant->update(['operating_hours' => $request->operating_hours]);

        return response()->json(['message' => 'Hours updated', 'operating_hours' => $restaurant->fresh()->operating_hours]);
    }

    // ═══════════════════════════════════════
    //  ORDERS
    // ═══════════════════════════════════════

    public function orders(Request $request)
    {
        $restaurant = $request->user();

        // Auto-cancel pending orders older than 5 minutes
        Order::where('restaurant_id', $restaurant->id)
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(5))
            ->update(['status' => 'cancelled']);

        $query = Order::where('restaurant_id', $restaurant->id)
            ->with(['items.menuItem', 'user', 'rider']);

        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->date) {
            $query->whereDate('created_at', $request->date);
        }

        return response()->json([
            'orders' => $query->orderBy('created_at', 'desc')->paginate(20),
        ]);
    }

    public function activeOrders(Request $request)
    {
        $restaurant = $request->user();

        // Auto-cancel pending orders older than 5 minutes
        Order::where('restaurant_id', $restaurant->id)
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(5))
            ->update(['status' => 'cancelled']);

        $orders = Order::where('restaurant_id', $restaurant->id)
            ->whereIn('status', ['pending', 'confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery'])
            ->with(['items.menuItem', 'user', 'rider'])
            ->orderByRaw("FIELD(status, 'pending', 'confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery')")
            ->orderBy('created_at', 'asc')
            ->get();

        $grouped = [
            'new' => $orders->where('status', 'pending')->values(),
            'confirmed' => $orders->where('status', 'confirmed')->values(),
            'preparing' => $orders->where('status', 'preparing')->values(),
            'ready' => $orders->where('status', 'ready_for_pickup')->values(),
            'out_for_delivery' => $orders->where('status', 'out_for_delivery')->values(),
        ];

        return response()->json([
            'orders' => $orders,
            'grouped' => $grouped,
            'counts' => [
                'new' => $grouped['new']->count(),
                'confirmed' => $grouped['confirmed']->count(),
                'preparing' => $grouped['preparing']->count(),
                'ready' => $grouped['ready']->count(),
                'out_for_delivery' => $grouped['out_for_delivery']->count(),
                'total' => $orders->count(),
            ],
        ]);
    }

    public function acceptOrder(Request $request, Order $order)
    {
        $restaurant = $request->user();
        if ($order->restaurant_id !== $restaurant->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        if ($order->status !== 'pending') {
            return response()->json(['error' => 'Order is no longer pending'], 422);
        }

        $extraMinutes = intval($request->get('extra_minutes', 0));
        if ($extraMinutes < 0 || $extraMinutes > 5) {
            $extraMinutes = 0;
        }

        $totalQty = $order->items()->sum('quantity');
        $basePrep = $totalQty <= 2 ? 15 : ($totalQty <= 4 ? 20 : 25);
        $prepTime = $basePrep + $extraMinutes;

        $order->update([
            'status' => 'preparing',
            'accepted_at' => now(),
            'prep_time_minutes' => $prepTime,
            'prep_deadline_at' => now()->addMinutes($prepTime),
        ]);

        // Notify customer that order is being prepared
        event(new OrderStatusChanged($order, 'pending', 'preparing'));

        // Dispatch rider assignment
        AssignRiderAfterAcceptance::dispatch($order->id);

        return response()->json(['message' => 'Order accepted!', 'order' => $order->fresh()->load(['items.menuItem', 'user', 'rider'])]);
    }

    /**
     * 30-day reliability snapshot: timeouts, rejects, late prep, miss rate.
     * Visible to the owner; the AM can coach but cannot edit it.
     */
    public function reliability(Request $request)
    {
        $restaurant = $request->user();
        $from = now()->subDays(30);

        $base = Order::where('restaurant_id', $restaurant->id)->where('created_at', '>=', $from);

        $total = (clone $base)->count();
        $timeouts = (clone $base)->where('cancel_reason', 'restaurant_timeout')->count();
        $rejects = (clone $base)->where('cancel_reason', 'restaurant_reject')->count();
        $latePrep = (clone $base)->where('prep_delay_minutes', '>', 0)->count();

        return response()->json([
            'window_days' => 30,
            'total_orders' => $total,
            'timeouts' => $timeouts,
            'rejects' => $rejects,
            'late_prep' => $latePrep,
            'miss_rate_percent' => $total > 0 ? round((($timeouts + $rejects) / $total) * 100, 2) : 0,
        ]);
    }

    public function rejectOrder(Request $request, Order $order)
    {
        $restaurant = $request->user();
        if ($order->restaurant_id !== $restaurant->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        if (! in_array($order->status, ['pending', 'confirmed'])) {
            return response()->json(['error' => 'Cannot reject at this stage'], 422);
        }

        $validated = $request->validate([
            'reason' => 'required|in:oos,closed,capacity,other',
            'notes' => 'required_if:reason,other|nullable|string|min:10|max:120',
        ]);

        $order->update([
            'status' => 'cancelled',
            'cancel_reason' => 'restaurant_reject',
            'cancel_note' => $validated['notes']
                ?? ucfirst($validated['reason']).' — rejected by restaurant',
        ]);

        // Customer never pays for a restaurant rejection.
        \App\Services\OrderReversalService::reverse($order, 'restaurant_reject');

        // Notify customer that order was cancelled
        event(new OrderStatusChanged($order, 'pending', 'cancelled'));

        return response()->json(['message' => 'Order rejected', 'reason' => $validated['reason']]);
    }

    public function updateOrderStatus(Request $request, Order $order)
    {
        $restaurant = $request->user();
        if ($order->restaurant_id !== $restaurant->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'status' => 'required|in:preparing,ready_for_pickup',
        ]);

        $allowed = [
            'confirmed' => ['preparing'],
            'preparing' => ['ready_for_pickup'],
        ];

        $current = $order->status;
        if (! isset($allowed[$current]) || ! in_array($request->status, $allowed[$current])) {
            return response()->json(['error' => "Cannot go from '{$current}' to '{$request->status}'"], 422);
        }

        $updateData = ['status' => $request->status];

        if ($request->status === 'ready_for_pickup') {
            $updateData['ready_marked_at'] = now();
            if ($order->prep_deadline_at) {
                $deadline = Carbon::parse($order->prep_deadline_at);
                $updateData['prep_delay_minutes'] = $deadline->diffInMinutes(now(), false);
            }
        }

        $order->update($updateData);

        event(new OrderStatusChanged($order, $order->status, $request->status));

        // Auto-trigger rider assignment when ready (if not already assigned)
        if ($request->status === 'ready_for_pickup') {
            $riderAssigned = (bool) $order->rider_id;
            if (! $riderAssigned) {
                $assignment = RiderAssignmentService::assign($order);
                $riderAssigned = (bool) $assignment;
            }

            return response()->json([
                'message' => 'Order status updated. '.($riderAssigned ? 'Rider assigned.' : 'Looking for rider...'),
                'status' => $request->status,
                'rider_assigned' => $riderAssigned,
            ]);
        }

        return response()->json(['message' => 'Order status updated', 'status' => $request->status]);
    }

    public function kitchenFull(Request $request, Order $order)
    {
        $restaurant = $request->user();
        if ($order->restaurant_id !== $restaurant->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'extra_minutes' => 'required|integer|min:5|max:60',
            'action' => 'sometimes|integer|in:0,1,2',
            'pause_duration' => 'sometimes|integer|nullable',
        ]);

        $action = $request->get('action', 0);
        $pauseDuration = $request->get('pause_duration');

        if ($action == 2) {
            // Cancel order
            $oldStatus = $order->status;
            $order->update(['status' => 'cancelled']);
            event(new OrderStatusChanged($order, $oldStatus, 'cancelled'));
        } elseif ($order->prep_deadline_at) {
            $order->update([
                'prep_deadline_at' => (new Carbon($order->prep_deadline_at))->addMinutes($request->extra_minutes),
                'prep_delay_minutes' => $order->prep_delay_minutes + $request->extra_minutes,
            ]);
            event(new OrderStatusChanged($order, $order->status, $order->status));
        }

        if (($action == 1 || $action == 2) && $pauseDuration > 0) {
            $pausedUntil = now()->addMinutes($pauseDuration);
            // Pause new orders (Toggle Offline)
            $restaurant->update([
                'is_accepting_orders' => false,
                'paused_until' => $pausedUntil,
                'manually_offline' => false,
            ]);
            Cache::put('restaurant_pause_'.$restaurant->id, true, $pausedUntil);
        }

        return response()->json(['message' => 'Action applied successfully', 'order' => $order->fresh()->load(['items.menuItem', 'user', 'rider'])]);
    }

    public function itemOutOfStock(Request $request, Order $order)
    {
        $restaurant = $request->user();
        if ($order->restaurant_id !== $restaurant->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'order_item_id' => 'required|exists:order_items,id',
            'action' => 'required|in:refund,substitute',
        ]);

        $item = $order->items()->where('id', $request->order_item_id)->first();
        if (! $item) {
            return response()->json(['error' => 'Item not in order'], 404);
        }

        if ($request->action === 'refund') {
            $refundAmount = $item->price * $item->quantity;
            $order->subtotal -= $refundAmount;
            $order->grand_total -= $refundAmount;
            $order->save();

            $item->delete();

            event(new OrderStatusChanged($order, $order->status, $order->status));
        }

        return response()->json(['message' => 'Item removed and order updated', 'order' => $order->fresh()->load(['items.menuItem', 'user', 'rider'])]);
    }

    public function monthlyGstReport(Request $request, $year, $month)
    {
        $restaurant = $request->user();
        $controller = app(GstController::class);

        return $controller->monthlyReport($request, $restaurant->id, $year, $month);
    }

    public function invoices(Request $request)
    {
        $restaurant = $request->user();

        return Invoice::where('restaurant_id', $restaurant->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function payments(Request $request)
    {
        $restaurant = $request->user();

        return VendorPayment::where('restaurant_id', $restaurant->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    // ═══════════════════════════════════════
    //  MENU MANAGEMENT
    // ═══════════════════════════════════════

    public function menu(Request $request)
    {
        $restaurant = $request->user();
        $categories = Category::where('restaurant_id', $restaurant->id)
            ->with(['menuItems.variantType.variants', 'menuItems.addOnGroups.items', 'menuItems.images'])
            ->orderBy('sort_order')
            ->get();

        $uncategorized = MenuItem::where('restaurant_id', $restaurant->id)
            ->whereNull('category_id')
            ->with(['variantType.variants', 'addOnGroups.items', 'images'])
            ->get();

        return response()->json([
            'categories' => $categories,
            'uncategorized' => $uncategorized,
            'total_items' => MenuItem::where('restaurant_id', $restaurant->id)->count(),
            'active_items' => MenuItem::where('restaurant_id', $restaurant->id)->where('is_available', true)->count(),
        ]);
    }

    public function storeMenuItem(Request $request)
    {
        $restaurant = $request->user();
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'required|string|max:500',
            'price' => 'required|numeric|gt:0',
            'category_id' => 'required|uuid|exists:categories,id',
            'prep_type' => 'required|in:Veg,Non-Veg,Vegan,Jain,Vaishno,Egg',
            'fasting_suitable' => 'sometimes|boolean',
            'tax_type' => 'required|in:GST_5,NONE,GST_18',
            'tax_included' => 'sometimes|boolean',
            'serves_count' => 'nullable|integer|min:1',
            'image_url' => 'sometimes|string',
            'is_available' => 'sometimes|boolean',
            'is_mrp' => 'sometimes|boolean',
            'modifiers' => 'sometimes|array',

            // Variant
            'variant_type' => 'nullable|array',
            'variant_type.axis' => 'required_with:variant_type|in:size,volume,quantity',
            'variant_type.unit' => 'nullable|string|max:20',
            'variants' => 'nullable|array|min:1',
            'variants.*.label' => 'required|string|max:100',
            'variants.*.unit' => 'nullable|string|max:20',
            'variants.*.price_impact' => 'required|numeric',
            'variants.*.is_default' => 'sometimes|boolean',

            // Add-on groups (pivot)
            'add_on_group_ids' => 'nullable|array',
            'add_on_group_ids.*' => 'uuid|exists:add_on_groups,id',

            // Images
            'images' => 'nullable|array|max:5',
            'images.*.image_url' => 'required|string',
            'images.*.is_cover' => 'sometimes|boolean',
            'images.*.sort_order' => 'sometimes|integer',

            // Packaging (item-level)
            'packaging_charge_level' => 'nullable|in:item_level',
            'packaging_charge_type' => 'required_if:packaging_charge_level,item_level|in:fixed',
            'packaging_charge_value' => 'required_if:packaging_charge_level,item_level|numeric|min:0',
            'packaging_tax_type' => 'nullable|in:GST_5,NONE',
        ]);

        $validated['restaurant_id'] = $restaurant->id;
        $item = MenuItem::create($validated);

        // Handle variants
        if ($request->has('variant_type')) {
            $vt = $item->variantType()->create($request->input('variant_type'));
            foreach ($request->input('variants', []) as $vData) {
                $vt->variants()->create($vData);
            }
        }

        // Handle add-on groups
        if ($request->has('add_on_group_ids')) {
            $item->addOnGroups()->sync($request->input('add_on_group_ids'));
        }

        // Handle images
        if ($request->has('images')) {
            foreach ($request->input('images') as $imgData) {
                $item->images()->create($imgData);
            }
        }

        $item->load(['variantType.variants', 'addOnGroups.items', 'images', 'category']);

        return response()->json(['message' => 'Item added', 'item' => $item], 201);
    }

    public function updateMenuItem(Request $request, MenuItem $item)
    {
        $restaurant = $request->user();
        if ($item->restaurant_id !== $restaurant->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'sometimes|string|max:500',
            'price' => 'sometimes|numeric|gt:0',
            'category_id' => 'sometimes|uuid|exists:categories,id',
            'prep_type' => 'sometimes|in:Veg,Non-Veg,Vegan,Jain,Vaishno,Egg',
            'fasting_suitable' => 'sometimes|boolean',
            'tax_type' => 'sometimes|in:GST_5,NONE,GST_18',
            'tax_included' => 'sometimes|boolean',
            'serves_count' => 'nullable|integer|min:1',
            'image_url' => 'sometimes|string',
            'is_available' => 'sometimes|boolean',
            'is_mrp' => 'sometimes|boolean',
            'modifiers' => 'sometimes|array',

            // Variant
            'variant_type' => 'nullable|array',
            'variant_type.axis' => 'required_with:variant_type|in:size,volume,quantity',
            'variant_type.unit' => 'nullable|string|max:20',
            'variants' => 'nullable|array|min:1',
            'variants.*.label' => 'required|string|max:100',
            'variants.*.unit' => 'nullable|string|max:20',
            'variants.*.price_impact' => 'required|numeric',
            'variants.*.is_default' => 'sometimes|boolean',

            // Add-on groups
            'add_on_group_ids' => 'nullable|array',
            'add_on_group_ids.*' => 'uuid|exists:add_on_groups,id',

            // Images
            'images' => 'nullable|array|max:5',
            'images.*.image_url' => 'required|string',
            'images.*.is_cover' => 'sometimes|boolean',
            'images.*.sort_order' => 'sometimes|integer',

            // Packaging
            'packaging_charge_level' => 'nullable|in:item_level',
            'packaging_charge_type' => 'required_if:packaging_charge_level,item_level|in:fixed',
            'packaging_charge_value' => 'required_if:packaging_charge_level,item_level|numeric|min:0',
            'packaging_tax_type' => 'nullable|in:GST_5,NONE',
        ]);

        $item->update($validated);

        // Handle variant replacement
        if ($request->has('variant_type')) {
            $item->variantType?->delete(); // cascades to variants
            $vt = $item->variantType()->create($request->input('variant_type'));
            foreach ($request->input('variants', []) as $vData) {
                $vt->variants()->create($vData);
            }
        }

        // Handle add-on groups
        if ($request->has('add_on_group_ids')) {
            $item->addOnGroups()->sync($request->input('add_on_group_ids'));
        }

        // Handle image replacement
        if ($request->has('images')) {
            $item->images()->delete();
            foreach ($request->input('images') as $imgData) {
                $item->images()->create($imgData);
            }
        }

        $item->load(['variantType.variants', 'addOnGroups.items', 'images', 'category']);

        return response()->json(['message' => 'Item updated', 'item' => $item]);
    }

    public function deleteMenuItem(Request $request, MenuItem $item)
    {
        $restaurant = $request->user();
        if ($item->restaurant_id !== $restaurant->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $item->delete();

        return response()->json(['message' => 'Item deleted']);
    }

    // ═══════════════════════════════════════
    //  CATEGORIES
    // ═══════════════════════════════════════

    public function storeCategory(Request $request)
    {
        $restaurant = $request->user();
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'sometimes|string',
            'sort_order' => 'sometimes|integer',
        ]);

        $validated['restaurant_id'] = $restaurant->id;
        $category = Category::create($validated);

        return response()->json(['message' => 'Category created', 'category' => $category], 201);
    }

    public function updateCategory(Request $request, Category $category)
    {
        $restaurant = $request->user();
        if ($category->restaurant_id !== $restaurant->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'sort_order' => 'sometimes|integer',
        ]);

        $category->update($validated);

        return response()->json(['message' => 'Category updated', 'category' => $category->fresh()]);
    }

    public function deleteCategory(Request $request, Category $category)
    {
        $restaurant = $request->user();
        if ($category->restaurant_id !== $restaurant->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Move items to uncategorized
        MenuItem::where('category_id', $category->id)->update(['category_id' => null]);
        $category->delete();

        return response()->json(['message' => 'Category deleted, items moved to uncategorized']);
    }

    // ═══════════════════════════════════════
    //  EARNINGS
    // ═══════════════════════════════════════

    public function earnings(Request $request)
    {
        $restaurant = $request->user();
        $period = $request->input('period', 'today'); // today, week, month

        $query = Order::where('restaurant_id', $restaurant->id)
            ->where('status', 'delivered');

        switch ($period) {
            case 'week':
                $query->where('created_at', '>=', now()->startOfWeek());
                break;
            case 'month':
                $query->where('created_at', '>=', now()->startOfMonth());
                break;
            default:
                $query->whereDate('created_at', today());
        }

        $orders = $query->get();
        $grossRevenue = $orders->sum('subtotal');
        $commissionAmount = round($grossRevenue * ($restaurant->commission_rate / 100), 2);
        $deliveryFeeShare = $orders->sum('delivery_fee_restaurant');
        $netPayout = round($grossRevenue - $commissionAmount - $deliveryFeeShare, 2);

        // Daily breakdown (last 7 days)
        $dailyBreakdown = Order::where('restaurant_id', $restaurant->id)
            ->where('status', 'delivered')
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(subtotal) as revenue')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        return response()->json([
            'period' => $period,
            'gross_revenue' => $grossRevenue,
            'commission_rate' => $restaurant->commission_rate,
            'commission_amount' => $commissionAmount,
            'delivery_fee_share' => $deliveryFeeShare,
            'net_payout' => $netPayout,
            'total_orders' => $orders->count(),
            'avg_order_value' => $orders->count() > 0 ? round($grossRevenue / $orders->count(), 2) : 0,
            'daily_breakdown' => $dailyBreakdown,
        ]);
    }

    public function earningsStatement(Request $request)
    {
        $restaurant = $request->user();
        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        $orders = Order::where('restaurant_id', $restaurant->id)
            ->where('status', 'delivered')
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->with('items.menuItem')
            ->get();

        $grossRevenue = $orders->sum('subtotal');
        $commissionAmount = round($grossRevenue * ($restaurant->commission_rate / 100), 2);
        $deliveryFeeShare = $orders->sum('delivery_fee_restaurant');
        $gstConfig = SystemConfig::where('config_key', 'commission_gst_rate')->first();
        $gstRate = $gstConfig ? (float) $gstConfig->config_value : 18.00;
        $gstOnCommission = round($commissionAmount * ($gstRate / 100), 2);
        $tds = round($commissionAmount * 0.01, 2);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Statement');

        $sheet->setCellValue('A1', 'KHAALO - Earnings Statement');
        $sheet->setCellValue('A2', "Restaurant: {$restaurant->name}");
        $sheet->setCellValue('A3', 'Period: '.sprintf('%04d-%02d', $year, $month));
        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $row = 5;
        $labels = [
            'Total Orders' => $orders->count(),
            'Gross Revenue' => $grossRevenue,
            "Platform Commission ({$restaurant->commission_rate}%)" => $commissionAmount,
            'Delivery Fee Share' => $deliveryFeeShare,
            "GST on Commission ({$gstRate}%)" => $gstOnCommission,
            'TDS Deducted (1%)' => $tds,
            'Net Payable' => round($grossRevenue - $commissionAmount - $deliveryFeeShare - $gstOnCommission - $tds, 2),
        ];
        foreach ($labels as $label => $value) {
            $sheet->setCellValue("A$row", $label);
            $sheet->setCellValue("B$row", $value);
            $sheet->getStyle("A$row")->getFont()->setBold(true);
            $row++;
        }

        $row += 2;
        $sheet->setCellValue("A$row", 'Order ID');
        $sheet->setCellValue("B$row", 'Date');
        $sheet->setCellValue("C$row", 'Subtotal');
        $sheet->setCellValue("D$row", 'Grand Total');
        $sheet->getStyle("A$row:D$row")->getFont()->setBold(true);
        $row++;
        foreach ($orders as $order) {
            $sheet->setCellValue("A$row", $order->id);
            $sheet->setCellValue("B$row", $order->created_at->format('Y-m-d'));
            $sheet->setCellValue("C$row", $order->subtotal);
            $sheet->setCellValue("D$row", $order->grand_total);
            $row++;
        }

        foreach (range('A', 'D') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        $filename = "Statement_{$restaurant->name}_{$year}_{$month}.xlsx";
        $tempFile = tempnam(sys_get_temp_dir(), 'stmt_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Restaurant rates the rider after pickup/handover.
     */
    public function rateRider(Request $request)
    {
        $restaurant = $request->user();

        $validated = $request->validate([
            'order_id' => 'required|uuid',
            'rider_id' => 'required|uuid',
            'rating' => 'required|integer|min:1|max:5',
            'feedback' => 'sometimes|string|max:500',
        ]);

        $order = Order::where('id', $validated['order_id'])
            ->where('restaurant_id', $restaurant->id)
            ->first();

        if (! $order) {
            return response()->json(['error' => 'Order not found or not yours.'], 404);
        }

        $order->update([
            'restaurant_rating_for_rider' => $validated['rating'],
            'restaurant_feedback_for_rider' => $validated['feedback'] ?? null,
        ]);

        $rider = Rider::find($validated['rider_id']);
        if ($rider) {
            RiderAssignmentService::updateEmaRating($rider, $validated['rating']);
        }

        return response()->json(['message' => 'Rider rating submitted.']);
    }

    /**
     * Get orders pending restaurant-to-rider rating.
     */
    public function pendingRiderRatings(Request $request)
    {
        $restaurant = $request->user();

        $orders = Order::where('restaurant_id', $restaurant->id)
            ->whereNull('restaurant_rating_for_rider')
            ->whereIn('status', ['picked_up', 'out_for_delivery', 'delivered', 'completed'])
            ->whereNotNull('rider_id')
            ->with(['rider:id,name,phone', 'items.menuItem'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json(['data' => $orders]);
    }

    public function getReels(Request $request)
    {
        $restaurant = $request->user();
        $reels = MenuItem::where('restaurant_id', $restaurant->id)
            ->whereNotNull('reel_url')
            ->get(['id', 'name', 'price', 'reel_url', 'image_url']);

        $formatted = $reels->map(fn ($m) => ['menu_item_id' => $m->id, 'name' => $m->name, 'url' => $m->reel_url]);

        return response()->json(['reels' => $formatted]);
    }

    public function saveReel(Request $request)
    {
        $restaurant = $request->user();
        $validated = $request->validate([
            'menu_item_id' => 'required|exists:menu_items,id',
            'url' => 'required|url',
        ]);

        $item = MenuItem::where('id', $validated['menu_item_id'])
            ->where('restaurant_id', $restaurant->id)
            ->firstOrFail();

        $item->update(['reel_url' => $validated['url']]);

        return response()->json(['message' => 'Reel saved!', 'menu_item_id' => $item->id, 'url' => $item->reel_url]);
    }

    // ═══════════════════════════════════════
    //  ADD-ON GROUPS
    // ═══════════════════════════════════════

    public function addOnGroups(Request $request)
    {
        $restaurant = $request->user();
        $groups = AddOnGroup::where('restaurant_id', $restaurant->id)
            ->with('items')
            ->orderBy('sort_order')
            ->get();

        return response()->json(['add_on_groups' => $groups]);
    }

    public function storeAddOnGroup(Request $request)
    {
        $restaurant = $request->user();
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'selection_type' => 'sometimes|in:single,multiple',
            'min_select' => 'sometimes|integer|min:0',
            'max_select' => 'sometimes|integer|min:0',
            'sort_order' => 'sometimes|integer',
        ]);
        $validated['restaurant_id'] = $restaurant->id;
        $group = AddOnGroup::create($validated);

        return response()->json(['message' => 'Add-on group created', 'add_on_group' => $group], 201);
    }

    public function updateAddOnGroup(Request $request, AddOnGroup $addOnGroup)
    {
        $restaurant = $request->user();
        if ($addOnGroup->restaurant_id !== $restaurant->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'selection_type' => 'sometimes|in:single,multiple',
            'min_select' => 'sometimes|integer|min:0',
            'max_select' => 'sometimes|integer|min:0',
            'sort_order' => 'sometimes|integer',
        ]);
        $addOnGroup->update($validated);

        return response()->json(['message' => 'Add-on group updated', 'add_on_group' => $addOnGroup->fresh()->load('items')]);
    }

    public function deleteAddOnGroup(Request $request, AddOnGroup $addOnGroup)
    {
        $restaurant = $request->user();
        if ($addOnGroup->restaurant_id !== $restaurant->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $addOnGroup->delete();

        return response()->json(['message' => 'Add-on group deleted']);
    }

    public function storeAddOnItem(Request $request, AddOnGroup $addOnGroup)
    {
        $restaurant = $request->user();
        if ($addOnGroup->restaurant_id !== $restaurant->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'tax_type' => 'sometimes|in:GST_5,NONE,GST_18',
            'is_available' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);
        $item = $addOnGroup->items()->create($validated);

        return response()->json(['message' => 'Add-on item added', 'add_on_item' => $item], 201);
    }

    public function updateAddOnItem(Request $request, AddOnItem $addOnItem)
    {
        $addOnGroup = $addOnItem->addOnGroup;
        $restaurant = $request->user();
        if ($addOnGroup->restaurant_id !== $restaurant->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'price' => 'sometimes|numeric|min:0',
            'tax_type' => 'sometimes|in:GST_5,NONE,GST_18',
            'is_available' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);
        $addOnItem->update($validated);

        return response()->json(['message' => 'Add-on item updated', 'add_on_item' => $addOnItem->fresh()]);
    }

    public function deleteAddOnItem(Request $request, AddOnItem $addOnItem)
    {
        $addOnGroup = $addOnItem->addOnGroup;
        $restaurant = $request->user();
        if ($addOnGroup->restaurant_id !== $restaurant->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $addOnItem->delete();

        return response()->json(['message' => 'Add-on item deleted']);
    }

    // ═══════════════════════════════════════
    //  PACKAGING SETTINGS
    // ═══════════════════════════════════════

    public function packagingSettings(Request $request)
    {
        $restaurant = $request->user();
        $settings = RestaurantPackagingSetting::where('restaurant_id', $restaurant->id)->first();

        return response()->json(['packaging_settings' => $settings]);
    }

    public function upsertPackagingSettings(Request $request)
    {
        $restaurant = $request->user();
        $validated = $request->validate([
            'charge_type' => 'required|in:fixed,percentage',
            'charge_value' => 'required|numeric|min:0',
            'tax_type' => 'sometimes|in:GST_5,NONE',
            'is_active' => 'sometimes|boolean',
        ]);

        $settings = RestaurantPackagingSetting::updateOrCreate(
            ['restaurant_id' => $restaurant->id],
            $validated
        );

        return response()->json(['message' => 'Packaging settings saved', 'packaging_settings' => $settings]);
    }
}
