<?php

namespace App\Http\Controllers\Api;

use App\Events\NewOrderForVendor;
use App\Events\OrderStatusChanged;
use App\Http\Controllers\Controller;
use App\Jobs\AssignRiderAfterAcceptance;
use App\Models\AddOnItem;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Referral;
use App\Models\Restaurant;
use App\Models\Rider;
use App\Models\RestaurantRating;
use App\Models\RiderRating;
use App\Models\SystemConfig;
use App\Models\User;
use App\Models\Variant;
use App\Models\WalletTransaction;
use App\Services\DiscountCalculationService;
use App\Services\FcmService;
use App\Services\KitchenInventoryService;
use App\Services\MenuPricingService;
use App\Services\OutboxPublisher;
use App\Services\PaymentService;
use App\Services\POSIntegrationService;
use App\Services\RiderAssignmentService;
use App\Services\RiderPayoutService;
use App\Services\SmartPrepTimeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * List authenticated user's orders (paginated, filterable by status)
     */
    public function index(Request $request)
    {
        $query = Order::where('user_id', $request->user()->id)
            ->with(['restaurant', 'items.menuItem'])
            ->latest();

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $orders = $query->paginate($request->get('per_page', 15));

        return response()->json($orders);
    }

    public function checkout(Request $request)
    {
        $channel = $request->input('channel', 'app');
        $isWhatsApp = $channel === 'whatsapp';

        if ($isWhatsApp) {
            $idempotencyKey = $request->header('Idempotency-Key');
            if (! $idempotencyKey || strlen($idempotencyKey) < 8 || strlen($idempotencyKey) > 80) {
                return response()->json([
                    'error' => 'Idempotency-Key header (8-80 chars) is required for WhatsApp orders.',
                ], 422);
            }

            $cached = DB::table('idempotency_keys')
                ->where('key', $idempotencyKey)
                ->where('user_id', $request->user()->id)
                ->first();

            if ($cached) {
                $stored = json_decode($cached->response, true);

                return response()->json($stored['body'] ?? [], $stored['code'] ?? 201);
            }
        }

        $validated = $request->validate([
            'channel' => 'sometimes|in:app,web,whatsapp',
            'restaurant_id' => 'required|uuid',
            'items' => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|uuid',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.variant_id' => 'sometimes|uuid|exists:variants,id',
            'items.*.add_on_item_ids' => 'sometimes|array',
            'items.*.add_on_item_ids.*' => 'uuid|exists:add_on_items,id',
            'items.*.special_instructions' => 'sometimes|string|max:200',
            'payment_mode' => $isWhatsApp ? 'required|in:prepaid,cod' : 'sometimes|in:prepaid,cod,wallet',
            'coupon_code' => 'sometimes|string',
            'address_id' => 'sometimes|string',
            'delivery_address_text' => 'sometimes|string|max:500',
            'delivery_address_lat' => 'sometimes|numeric',
            'delivery_address_lng' => 'sometimes|numeric',
            'customer_tip' => $isWhatsApp ? 'sometimes|numeric|min:0|max:500' : 'sometimes|numeric|min:0',
            'auto_apply_offers' => 'sometimes|boolean',
            'is_business_order' => 'sometimes|boolean',
            'company_name' => 'required_if:is_business_order,true|nullable|string|max:150',
            'company_gst' => 'required_if:is_business_order,true|nullable|string|size:15',
        ]);

        // Validate minimum order amount
        $restaurant = Restaurant::findOrFail($validated['restaurant_id']);
        if (! $restaurant->is_active) {
            return response()->json(['error' => 'Restaurant is currently inactive.'], 422);
        }
        if (! $restaurant->is_accepting_orders) {
            return response()->json(['error' => 'Restaurant is closed and not accepting orders.'], 422);
        }
        if ($restaurant->minimum_order_amount > 0) {
            // We'll validate after calculating subtotal
        }

        // City hourly capacity (opt-in): festival/supply protection.
        $cityCap = (int) (SystemConfig::where('config_key', 'city_hourly_order_cap')->value('config_value') ?? 0);
        if ($restaurant->city_id) {
            $cityCap = (int) (SystemConfig::where('config_key', 'city_hourly_order_cap_'.$restaurant->city_id)->value('config_value') ?? $cityCap);
        }

        if (! app(\App\Services\City\CityCapacityGuard::class)->allow($restaurant->city_id, $cityCap)) {
            return response()->json([
                'error' => 'This area has hit its order capacity for the hour. Please try again shortly — no payment has been taken.',
                'code' => 'city_capacity',
            ], 422);
        }

        // Supply gate (opt-in): do not take money in a city with zero online riders.
        $blockWithoutRiders = filter_var(
            SystemConfig::where('config_key', 'block_checkout_without_riders')->value('config_value') ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
        if ($blockWithoutRiders) {
            $onlineRiders = Rider::where('is_online', true)
                ->where('is_verified', true)
                ->whereNotNull('latitude')
                ->where('updated_at', '>=', now()->subMinutes(30))
                ->count();

            if ($onlineRiders === 0) {
                return response()->json([
                    'error' => 'Delivery partners are currently offline in your area. Please try again shortly — no payment has been taken.',
                ], 422);
            }
        }

        // Validate delivery area (strictly capped at 10.0 km range)
        $address = null;
        $distance = null;
        $deliveryRadius = min((float) ($restaurant->delivery_radius_km ?? 10.0), 10.0);
        if ($restaurant->latitude && $restaurant->longitude && isset($validated['address_id'])) {
            $address = Address::find($validated['address_id']);
            if ($address && $address->latitude && $address->longitude) {
                $distance = self::haversineDistance(
                    $restaurant->latitude, $restaurant->longitude,
                    $address->latitude, $address->longitude
                );
                if ($distance > $deliveryRadius) {
                    return response()->json(['error' => "Delivery address is outside the restaurant's delivery area ({$deliveryRadius} km radius)"], 422);
                }
            }
        }

        try {
            DB::beginTransaction();

            if ($isWhatsApp) {
                $keyInserted = DB::table('idempotency_keys')->insertOrIgnore([
                    'key' => $idempotencyKey,
                    'user_id' => $request->user()->id,
                    'order_id' => null,
                    'response' => json_encode(['code' => 409, 'body' => ['error' => 'Order request already in progress.'], 'pending' => true]),
                    'created_at' => now(),
                ]);

                if (! $keyInserted) {
                    DB::rollBack();

                    $cached = DB::table('idempotency_keys')
                        ->where('key', $idempotencyKey)
                        ->where('user_id', $request->user()->id)
                        ->first();

                    $stored = $cached ? json_decode($cached->response, true) : null;

                    return response()->json(
                        $stored['body'] ?? ['error' => 'Order request already in progress.'],
                        $stored['code'] ?? 409,
                    );
                }
            }

            $user = $request->user();
            $subtotal = 0;
            $platformCommission = 0;

            // Get Configs
            $taxRate = 0;
            if (! empty($restaurant->gst_number)) {
                $taxRate = (float) (SystemConfig::where('config_key', 'platform_tax_rate')->value('config_value') ?? 5);
            }
            $vendorDiscountRate = 5; // Mandatory customer discount

            $paymentMode = $validated['payment_mode'] ?? 'prepaid';
            $deliveryOtp = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);

            $order = Order::create([
                'user_id' => $user->id,
                'restaurant_id' => $validated['restaurant_id'],
                'city_id' => $restaurant->city_id,
                'address_id' => $address ? $address->id : null,
                'delivery_address_text' => $validated['delivery_address_text']
                    ?? ($address ? "{$address->flat_building}, {$address->street}, {$address->landmark}, {$address->city}, {$address->pincode}" : null),
                'delivery_address_lat' => $request->input('delivery_address_lat') ?? ($address ? $address->latitude : null),
                'delivery_address_lng' => $request->input('delivery_address_lng') ?? ($address ? $address->longitude : null),
                'status' => 'pending',
                'channel' => $validated['channel'] ?? 'app',
                'payment_mode' => $paymentMode,
                'delivery_otp' => $deliveryOtp,
                'subtotal' => 0,
                'tax_amount' => 0,
                'vendor_discount_amount' => 0,
                'platform_commission' => 0,
                'grand_total' => 0,
                'is_business_order' => $validated['is_business_order'] ?? false,
                'company_name' => $validated['company_name'] ?? null,
                'company_gst' => $validated['company_gst'] ?? null,
            ]);

            foreach ($validated['items'] as $itemData) {
                $menuItem = MenuItem::findOrFail($itemData['menu_item_id']);

                // Resolve variant pricing
                $variant = null;
                $variantPrice = 0;
                $variantSnapshot = null;
                if (! empty($itemData['variant_id'])) {
                    $variant = Variant::find($itemData['variant_id']);
                    if ($variant) {
                        $variantPrice = (float) $variant->price_impact;
                        $variantSnapshot = [
                            'id' => $variant->id,
                            'label' => $variant->label,
                            'unit' => $variant->unit,
                            'price_impact' => $variant->price_impact,
                        ];
                    }
                }

                // Resolve add-ons
                $addOnSubtotal = 0;
                $addOnTax = 0;
                $addOnsSnapshot = [];
                if (! empty($itemData['add_on_item_ids'])) {
                    $addOnItems = AddOnItem::whereIn('id', $itemData['add_on_item_ids'])->get();
                    foreach ($addOnItems as $addOn) {
                        $addOnPrice = (float) $addOn->price;
                        $addOnSubtotal += $addOnPrice;
                        $addOnTaxRate = MenuPricingService::TAX_RATES[$addOn->tax_type] ?? 0;
                        $addOnTax += $addOnPrice * $addOnTaxRate;
                        $addOnsSnapshot[] = [
                            'id' => $addOn->id,
                            'name' => $addOn->name,
                            'price' => $addOnPrice,
                            'tax_type' => $addOn->tax_type,
                        ];
                    }
                }

                $unitPrice = (float) $menuItem->price + $variantPrice;
                $itemTotal = $unitPrice * $itemData['quantity'];
                $subtotal += $itemTotal;

                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $menuItem->id,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $unitPrice,
                    'is_mrp' => $menuItem->is_mrp,
                    'total_price' => $itemTotal,
                    'variant_snapshot' => $variantSnapshot,
                    'addons_snapshot' => $addOnsSnapshot,
                    'addon_subtotal' => $addOnSubtotal,
                    'addon_tax' => $addOnTax,
                    'special_instructions' => $itemData['special_instructions'] ?? null,
                ]);
            }

            // Calculations
            $vendorDiscountAmount = $subtotal * ($vendorDiscountRate / 100);
            $amountAfterDiscount = $subtotal - $vendorDiscountAmount;
            $taxAmount = $amountAfterDiscount * ($taxRate / 100);
            $grandTotal = $amountAfterDiscount + $taxAmount;

            // Validate minimum order amount
            if ($restaurant->minimum_order_amount > 0 && $subtotal < $restaurant->minimum_order_amount) {
                DB::rollBack();

                return response()->json([
                    'error' => "Minimum order amount is ₹{$restaurant->minimum_order_amount}. Current subtotal: ₹{$subtotal}",
                    'minimum_order_amount' => $restaurant->minimum_order_amount,
                    'current_subtotal' => $subtotal,
                ], 422);
            }

            // ─── Delivery Fee Calculation & 3-way Split ───
            $deliveryFreeAbove = (float) (SystemConfig::where('config_key', 'delivery_fee_free_above')->value('config_value') ?? 500);
            $deliveryFeeMin = (float) (SystemConfig::where('config_key', 'delivery_fee_min')->value('config_value') ?? 10);
            $deliveryFeeMax = (float) (SystemConfig::where('config_key', 'delivery_fee_max')->value('config_value') ?? 50);
            $customerPct = (float) (SystemConfig::where('config_key', 'delivery_fee_customer_pct')->value('config_value') ?? 60);
            $restaurantPct = (float) (SystemConfig::where('config_key', 'delivery_fee_restaurant_pct')->value('config_value') ?? 25);
            $platformPct = (float) (SystemConfig::where('config_key', 'delivery_fee_platform_pct')->value('config_value') ?? 15);

            // Estimate delivery cost based on rider payout rates
            $riderBasePay = (float) (SystemConfig::where('config_key', 'rider_base_pay')->value('config_value') ?? 15);
            $riderPerKm = (float) (SystemConfig::where('config_key', 'rider_per_km_rate')->value('config_value') ?? 4);
            $estimatedDeliveryCost = $riderBasePay + (3 * $riderPerKm); // assume ~3km avg
            $deliveryFee = round(max($deliveryFeeMin, min($estimatedDeliveryCost, $deliveryFeeMax)), 2);

            // Free delivery above threshold
            if ($subtotal >= $deliveryFreeAbove) {
                $deliveryFee = 0;
            }

            // Split the delivery cost (Customer pays 100%)
            $deliveryFeeCustomer = $deliveryFee;
            $deliveryFeeRestaurant = 0.00;
            $deliveryFeePlatform = 0.00;

            $platformFee = (float) (SystemConfig::where('config_key', 'platform_fee')->value('config_value') ?? 5.00);

            // Customer pays: food + tax + delivery + platform fee
            $grandTotal += $deliveryFeeCustomer + $platformFee;

            // Apply discounts via DiscountCalculationService
            $couponDiscount = 0;
            $appliedCouponId = null;
            $discountType = null;
            $discountLabel = null;

            $coupon = null;
            if (! empty($validated['coupon_code'])) {
                $coupon = \App\Models\Coupon::where('code', $validated['coupon_code'])
                    ->where('is_active', true)
                    ->first();

                if ($coupon) {
                    $now = now();
                    if ($coupon->valid_from && $now->lt($coupon->valid_from)) {
                        $coupon = null;
                    }
                    if ($coupon->valid_until && $now->gt($coupon->valid_until)) {
                        $coupon = null;
                    }
                    if ($coupon && $coupon->per_user_limit !== null) {
                        $usageCount = \App\Models\CouponUsage::where('coupon_id', $coupon->id)
                            ->where('user_id', $user->id)
                            ->count();
                        if ($usageCount >= $coupon->per_user_limit) {
                            $coupon = null;
                        }
                    }
                }
            }

            // Build cart items for discount evaluation
            $cartItems = collect($validated['items'])->map(fn ($i) => [
                'menu_item_id' => $i['menu_item_id'],
                'quantity'     => (int) ($i['quantity'] ?? 1),
                'unit_price'   => (float) ($i['unit_price'] ?? 0),
            ]);

            $activeBogos = \App\Models\BogoDiscount::with('menuItems')
                ->where('restaurant_id', $restaurant->id)
                ->where('is_active', true)
                ->get();

            $activeCampaigns = \App\Models\OptInCampaign::where('is_active', true)->get();

            $discountSvc = app(DiscountCalculationService::class);
            $bestDiscount = $discountSvc->calculateBestDiscount(
                $subtotal,
                $activeBogos,
                $activeCampaigns,
                $cartItems,
                $coupon,
                $restaurant->id,
                $user->id,
            );

            $couponDiscount = $bestDiscount['discount_amount'];
            $discountType   = $bestDiscount['discount_type'];
            $discountLabel  = $bestDiscount['label'];

            if ($couponDiscount > 0) {
                $couponDiscount = min($couponDiscount, $grandTotal);
                $grandTotal -= $couponDiscount;

                if ($bestDiscount['coupon_id']) {
                    $appliedCouponId = $bestDiscount['coupon_id'];
                    \App\Models\CouponUsage::create([
                        'coupon_id'       => $bestDiscount['coupon_id'],
                        'user_id'         => $user->id,
                        'order_id'        => $order->id,
                        'discount_amount' => $couponDiscount,
                    ]);
                    \App\Models\Coupon::where('id', $bestDiscount['coupon_id'])->increment('times_used');
                }
            }

            // Customer Tip
            $customerTip = (float) ($validated['customer_tip'] ?? 0);
            $grandTotal += $customerTip;

            // Handle Wallet Payment Mode
            if ($paymentMode === 'wallet') {
                if ($user->wallet_balance < $grandTotal) {
                    throw new \Exception('Insufficient wallet balance.');
                }

                $user->decrement('wallet_balance', $grandTotal);

                WalletTransaction::create([
                    'user_id' => $user->id,
                    'amount' => $grandTotal,
                    'type' => 'debit',
                    'description' => "Paid for Order #{$order->id}",
                ]);
            }

            $netOrderValue = $subtotal - $vendorDiscountAmount - $couponDiscount;
            $platformCommission = max(0, $netOrderValue * 0.15);
            $commissionGst = $platformCommission * 0.18;
            $netPayout = ($netOrderValue + $taxAmount) - ($platformCommission + $commissionGst);

            $order->update([
                'subtotal' => $subtotal,
                'vendor_discount_amount' => $vendorDiscountAmount,
                'tax_amount' => $taxAmount,
                'platform_fee' => $platformFee,
                'platform_commission' => $platformCommission,
                'net_payout' => max(0, $netPayout),
                'delivery_fee' => $deliveryFee,
                'delivery_fee_customer' => $deliveryFeeCustomer,
                'delivery_fee_restaurant' => $deliveryFeeRestaurant,
                'delivery_fee_platform' => $deliveryFeePlatform,
                'grand_total' => $grandTotal,
                'delivery_address_lat' => $request->input('delivery_address_lat') ?? ($address ? $address->latitude : null),
                'delivery_address_lng' => $request->input('delivery_address_lng') ?? ($address ? $address->longitude : null),
                'delivery_distance_km' => $distance,
                'payment_status' => $paymentMode === 'wallet' ? 'paid' : 'pending',
                'coupon_id' => $appliedCouponId,
                'coupon_discount_amount' => $couponDiscount,
            ]);

            // Delivery ETA Calculation
            $avgPrepTime = 15; // minutes
            $avgDeliveryTime = 20; // minutes
            $estimatedPickupTime = now()->addMinutes($avgPrepTime);
            $estimatedDeliveryTime = $estimatedPickupTime->copy()->addMinutes($avgDeliveryTime);

            // Clear customer cart
            Cart::where('user_id', $user->id)->delete();

            // Auto-accept immediately if the restaurant has it enabled
            if ($restaurant->auto_accept_orders) {
                $order->update([
                    'status' => 'preparing',
                    'accepted_at' => now(),
                    'prep_time_minutes' => $avgPrepTime,
                    'prep_deadline_at' => now()->addMinutes($avgPrepTime),
                ]);

                // Notify customer that order is being prepared
                event(new OrderStatusChanged($order, 'pending', 'preparing'));

                // Schedule rider assignment 1 minute after acceptance
                AssignRiderAfterAcceptance::dispatch($order->id)->delay(now()->addMinute());
            }

            DB::commit();

            // COD/Wallet → notify vendor immediately. Prepaid → notify after payment confirmed.
            if ($paymentMode !== 'prepaid') {
                event(new NewOrderForVendor($order, $order->restaurant_id));
            }

            $responseBody = [
                'message' => 'Order placed successfully!',
                'order' => $order->load('items'),
                'coupon_discount' => $couponDiscount,
                'wallet_balance' => (float) $user->fresh()->wallet_balance,
                'estimated_pickup_time' => $estimatedPickupTime->toIso8601String(),
                'estimated_delivery_time' => $estimatedDeliveryTime->toIso8601String(),
                'delivery_eta_minutes' => $avgPrepTime + $avgDeliveryTime,
            ];

            if ($isWhatsApp) {
                DB::table('idempotency_keys')
                    ->where('key', $idempotencyKey)
                    ->where('user_id', $user->id)
                    ->update([
                        'order_id' => $order->id,
                        'response' => json_encode(['code' => 201, 'body' => $responseBody]),
                    ]);
            }

            return response()->json($responseBody, 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['error' => 'Checkout failed: '.$e->getMessage()], 500);
        }
    }

    public function show(Order $order)
    {
        return response()->json([
            'order' => $order->load(['items.menuItem', 'restaurant', 'address']),
        ]);
    }

    /**
     * Restaurant accepts the order → status moves to 'confirmed'
     */
    public function acceptOrder(Order $order)
    {
        if ($order->status !== 'pending') {
            return response()->json(['error' => 'Order cannot be accepted in its current state.'], 422);
        }

        $extraMinutes = intval(request()->get('extra_minutes', 0));
        if ($extraMinutes < 0 || $extraMinutes > 5) {
            $extraMinutes = 0;
        }

        $totalQty = $order->items()->sum('quantity');
        $basePrep = $totalQty <= 2 ? 15 : ($totalQty <= 4 ? 20 : 25);
        $prepTime = $basePrep + $extraMinutes;

        // Use smart prep time prediction
        try {
            $smartPrep = app(SmartPrepTimeService::class)->predictPrepTime($order);
            if ($smartPrep > 0) {
                $prepTime = $smartPrep + $extraMinutes;
            }
        } catch (\Throwable $e) {
            Log::warning("SmartPrep failed: {$e->getMessage()}");
        }

        $order->update([
            'status' => 'preparing',
            'accepted_at' => now(),
            'prep_time_minutes' => $prepTime,
            'prep_deadline_at' => now()->addMinutes($prepTime),
        ]);

        event(new OrderStatusChanged($order, 'pending', 'preparing'));

        // Push order to POS if configured
        try {
            app(POSIntegrationService::class)->pushOrder($order);
        } catch (\Throwable $e) {
            Log::warning("POS push failed for order {$order->id}: {$e->getMessage()}");
        }

        // Auto-deduct inventory if enabled
        try {
            $inventoryService = app(KitchenInventoryService::class);
            foreach ($order->items as $item) {
                $inventoryService->deductForOrder($order->restaurant_id, $item->menu_item_id, $item->quantity);
            }
        } catch (\Throwable $e) {
            Log::warning("Inventory deduction failed for order {$order->id}: {$e->getMessage()}");
        }

        AssignRiderAfterAcceptance::dispatch($order->id)->delay(now()->addMinute());

        return response()->json(['message' => 'Order accepted!', 'order' => $order->fresh()->load(['items.menuItem', 'restaurant', 'rider'])]);
    }

    /**
     * Update order status (for restaurant/admin progression)
     */
    public function updateStatus(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'required|in:confirmed,preparing,ready_for_pickup,out_for_delivery,delivered,cancelled',
            'delivery_otp' => 'sometimes|string|size:4',
        ]);

        $allowed = [
            'pending' => ['confirmed', 'preparing', 'cancelled'],
            'confirmed' => ['preparing', 'cancelled'],
            'preparing' => ['ready_for_pickup'],          // restaurant marks ready
            'ready_for_pickup' => ['out_for_delivery'],    // rider picks up
            'out_for_delivery' => ['delivered'],            // rider delivers (OTP required for prepaid)
        ];

        $current = $order->status;
        if (! isset($allowed[$current]) || ! in_array($request->status, $allowed[$current])) {
            return response()->json(['error' => "Cannot transition from '{$current}' to '{$request->status}'."], 422);
        }

        // OTP verification for prepaid delivery
        if ($request->status === 'delivered' && $order->payment_mode === 'prepaid' && $order->delivery_otp) {
            if (! $request->delivery_otp || $request->delivery_otp !== $order->delivery_otp) {
                return response()->json(['error' => 'Invalid delivery OTP. Please ask the customer for the correct OTP.'], 422);
            }
        }

        // Proof of delivery (opt-in): required before a disputed delivery can close.
        if ($this->podRequired() && ! $order->delivery_photo_path) {
            return response()->json(['error' => 'Proof-of-delivery photo is required before marking delivered.'], 422);
        }


        $updateData = ['status' => $request->status];

        // Track timestamps for payout calculations
        if ($request->status === 'ready_for_pickup') {
            $updateData['ready_marked_at'] = now();
            if ($order->prep_deadline_at) {
                $deadline = Carbon::parse($order->prep_deadline_at);
                $updateData['prep_delay_minutes'] = $deadline->diffInMinutes(now(), false);
            }
        }
        if ($request->status === 'out_for_delivery') {
            $updateData['rider_picked_up_at'] = now();
            if ($order->ready_marked_at && $order->rider_arrived_at) {
                $handoverStart = max(Carbon::parse($order->ready_marked_at), Carbon::parse($order->rider_arrived_at));
                $handoverDeadline = $handoverStart->copy()->addMinutes(3);
                if (now()->gt($handoverDeadline)) {
                    $updateData['handover_delay_minutes'] = $handoverDeadline->diffInMinutes(now());
                }
            }
        }
        if ($request->status === 'delivered') {
            $updateData['delivered_at'] = now();
        }

        $order->update($updateData);

        // Dispatch status change notification to customer
        event(new OrderStatusChanged($order, $current, $request->status));

        // ─── Auto-trigger: Rider Assignment on ready_for_pickup ───
        if ($request->status === 'ready_for_pickup') {
            $assignment = RiderAssignmentService::assign($order);

            return response()->json([
                'message' => 'Status updated. '.($assignment ? 'Rider assigned!' : 'No riders available — waiting.'),
                'order' => $order->fresh()->load('rider'),
                'rider_assigned' => (bool) $assignment,
            ]);
        }

        // ─── Auto-trigger: Payout Calculation on delivered ───
        if ($request->status === 'delivered') {
            $payout = RiderPayoutService::calculate($order);

            // Process Referral reward if this is the customer's first delivered order
            $deliveredCount = Order::where('user_id', $order->user_id)
                ->where('status', 'delivered')
                ->count();

            if ($deliveredCount === 1) {
                // Find if there is a pending referral for this user
                $referral = Referral::where('referred_id', $order->user_id)
                    ->where('status', 'pending')
                    ->first();

                if ($referral) {
                    $rewardType = SystemConfig::where('config_key', 'referral_reward_type')->value('config_value') ?? 'fixed';
                    $rewardAmount = (float) (SystemConfig::where('config_key', 'referral_reward_amount')->value('config_value') ?? 100);
                    $maxCap = (float) (SystemConfig::where('config_key', 'referral_reward_max_cap')->value('config_value') ?? 200);
                    $referredReward = (float) (SystemConfig::where('config_key', 'referred_user_reward')->value('config_value') ?? 50);

                    // Calculate referrer reward
                    $referrerReward = 0.00;
                    if ($rewardType === 'percentage') {
                        $referrerReward = round($order->subtotal * ($rewardAmount / 100), 2);
                        if ($maxCap > 0) {
                            $referrerReward = min($referrerReward, $maxCap);
                        }
                    } else {
                        $referrerReward = $rewardAmount;
                    }

                    try {
                        DB::beginTransaction();

                        // Credit referrer
                        $referrer = User::find($referral->referrer_id);
                        if ($referrer) {
                            $referrer->increment('wallet_balance', $referrerReward);
                            WalletTransaction::create([
                                'user_id' => $referrer->id,
                                'amount' => $referrerReward,
                                'type' => 'credit',
                                'description' => "Referral bonus for inviting {$order->user->name}",
                            ]);

                            // Send push notification to referrer
                            app(FcmService::class)->sendToUser(
                                $referrer,
                                'customer',
                                [
                                    'type' => 'wallet_credit',
                                    'amount' => (string) $referrerReward,
                                    'screen' => 'wallet',
                                ],
                                'Wallet Credited!',
                                'You earned ₹'.number_format($referrerReward, 2)." as a referral reward for inviting {$order->user->name}!"
                            );
                        }

                        // Credit referred user
                        $referred = $order->user;
                        if ($referred) {
                            $referred->increment('wallet_balance', $referredReward);
                            WalletTransaction::create([
                                'user_id' => $referred->id,
                                'amount' => $referredReward,
                                'type' => 'credit',
                                'description' => 'Referral code welcome bonus',
                            ]);

                            // Send push notification to referred
                            app(FcmService::class)->sendToUser(
                                $referred,
                                'customer',
                                [
                                    'type' => 'wallet_credit',
                                    'amount' => (string) $referredReward,
                                    'screen' => 'wallet',
                                ],
                                'Wallet Credited!',
                                'You earned ₹'.number_format($referredReward, 2).' welcome bonus for using referral code!'
                            );
                        }

                        // Complete referral record
                        $referral->update([
                            'status' => 'completed',
                            'referrer_reward' => $referrerReward,
                            'referred_reward' => $referredReward,
                            'completed_at' => now(),
                        ]);

                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::error('Referral credit failed: '.$e->getMessage());
                    }
                }
            }

            return response()->json([
                'message' => 'Delivered! Payout calculated.',
                'order' => $order->fresh(),
                'payout' => $payout,
            ]);
        }

        return response()->json(['message' => 'Status updated.', 'order' => $order->fresh()]);
    }

    /**
     * Verify delivery OTP (standalone endpoint for riders)
     */
    public function verifyDeliveryOtp(Request $request, Order $order)
    {
        $request->validate(['otp' => 'required|string|size:4']);

        if ($order->status !== 'out_for_delivery') {
            return response()->json(['error' => 'Order is not out for delivery.'], 422);
        }

        if ($order->delivery_otp && $request->otp !== $order->delivery_otp) {
            return response()->json(['error' => 'Invalid OTP.', 'valid' => false], 422);
        }

        // Proof of delivery (opt-in): required before a disputed delivery can close.
        if ($this->podRequired() && ! $order->delivery_photo_path) {
            return response()->json(['error' => 'Proof-of-delivery photo is required before marking delivered.'], 422);
        }

        $order->update(['status' => 'delivered', 'delivered_at' => now()]);

        // Auto-calculate payout
        $payout = RiderPayoutService::calculate($order);

        return response()->json([
            'message' => 'Delivery confirmed!',
            'valid' => true,
            'order' => $order->fresh(),
            'payout' => $payout,
        ]);
    }

    /**
     * Mark rider as arrived at the restaurant
     */
    public function riderArrived(Order $order)
    {
        $order->update([
            'rider_arrived_at' => now(),
        ]);

        return response()->json([
            'message' => 'Rider marked as arrived!',
            'order' => $order->fresh()->load(['items.menuItem', 'user', 'rider']),
        ]);
    }

    /**
     * Rider reports the customer unreachable. Starts a 5-minute clock; the
     * sweep opens a support ticket and abandons the order. No rider strike.
     */
    public function customerUnreachable(Request $request, Order $order)
    {
        if ($order->rider_id !== $request->user()->id) {
            return response()->json(['error' => 'Not your delivery.'], 403);
        }

        if ($order->status !== 'out_for_delivery') {
            return response()->json(['error' => 'Order is not out for delivery.'], 422);
        }

        $validated = $request->validate([
            'attempts' => 'sometimes|integer|min:1|max:3',
            'notes' => 'sometimes|string|max:200',
        ]);

        $order->update(['customer_unreachable_at' => now()]);

        app(OutboxPublisher::class)->enqueue('order', $order->id, 'order.customer_unreachable', [
            'order_id' => $order->id,
            'rider_id' => $order->rider_id,
            'attempts' => $validated['attempts'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ], $order->restaurant_id);

        return response()->json([
            'message' => 'Marked unreachable. Support will step in after 5 minutes if the order is still open.',
            'window_minutes' => 5,
        ]);
    }

    /**
     * Switch a COD order to Prepaid (Online Payment)
     */
    public function payOnline(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        if (in_array($order->status, ['delivered', 'cancelled'])) {
            return response()->json(['error' => 'Order is already '.$order->status], 422);
        }

        if ($order->payment_status === 'paid') {
            return response()->json(['error' => 'Order is already paid.'], 422);
        }

        $validated = $request->validate([
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        $result = PaymentService::verifyPayment(
            $validated['razorpay_order_id'],
            $validated['razorpay_payment_id'],
            $validated['razorpay_signature']
        );

        if (! $result['verified']) {
            return response()->json(['error' => 'Payment verification failed.'], 422);
        }

        $order->update([
            'payment_mode' => 'prepaid',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'razorpay_payment_id' => $validated['razorpay_payment_id'],
            'razorpay_order_id' => $validated['razorpay_order_id'],
            'delivery_otp' => $order->delivery_otp ?? str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT),
        ]);

        return response()->json([
            'message' => 'Payment successful! Order upgraded to Prepaid.',
            'order' => $order->fresh()->load(['items.menuItem', 'restaurant']),
        ]);
    }

    /**
     * Convert a failed/cancelled prepaid order to COD
     */
    public function convertToCod(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        if ($order->payment_mode !== 'prepaid') {
            return response()->json(['error' => 'Order is not a prepaid order.'], 422);
        }

        if ($order->payment_status === 'paid') {
            return response()->json(['error' => 'Order is already paid.'], 422);
        }

        if (! in_array($order->status, ['pending'])) {
            return response()->json(['error' => 'Order can only be converted before restaurant accepts it.'], 422);
        }

        $order->update([
            'payment_mode' => 'cod',
            'delivery_otp' => null,
        ]);

        // Notify vendor now since it's COD
        event(new NewOrderForVendor($order, $order->restaurant_id));

        return response()->json([
            'message' => 'Order converted to Cash on Delivery.',
            'order' => $order->fresh(),
        ]);
    }

    /**
     * Customer cancels their own order (only before preparation)
     */
    public function cancelOrder(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        if (! in_array($order->status, ['pending', 'confirmed'])) {
            return response()->json(['error' => 'Order can only be cancelled before preparation starts.'], 422);
        }

        $order->update([
            'status' => 'cancelled',
            'cancel_reason' => 'customer',
        ]);

        // Formula reversal: wallet credit or gateway refund. No approval needed.
        \App\Services\OrderReversalService::reverse($order, 'customer');

        return response()->json([
            'message' => 'Order cancelled successfully.',
            'order' => $order->fresh(),
        ]);
    }

    private function podRequired(): bool
    {
        return filter_var(
            SystemConfig::where('config_key', 'require_pod_on_delivery')->value('config_value') ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * Calculate distance between two coordinates using Haversine formula
     */
    private static function haversineDistance($lat1, $lng1, $lat2, $lng2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Get orders where the customer has not yet rated the restaurant and/or rider.
     */
    public function pendingRatings(Request $request)
    {
        $user = $request->user();

        $deliveredOrders = Order::where('user_id', $user->id)
            ->whereIn('status', ['delivered', 'completed'])
            ->with(['restaurant:id,name,logo_url', 'rider:id,name'])
            ->orderByDesc('delivered_at')
            ->limit(50)
            ->get();

        $pending = $deliveredOrders->map(function ($order) {
            $customerRestaurantRating = RestaurantRating::where('order_id', $order->id)
                ->where('user_id', $order->user_id)
                ->exists();
            $customerRiderRating = RiderRating::where('order_id', $order->id)
                ->where('user_id', $order->user_id)
                ->exists();

            return [
                'order_id' => $order->id,
                'restaurant' => $order->restaurant ? ['id' => $order->restaurant->id, 'name' => $order->restaurant->name] : null,
                'rider' => $order->rider ? ['id' => $order->rider->id, 'name' => $order->rider->name] : null,
                'delivered_at' => $order->delivered_at,
                'total' => $order->grand_total,
                'needs_restaurant_rating' => ! $customerRestaurantRating,
                'needs_rider_rating' => ! $customerRiderRating && $order->rider_id,
            ];
        })->filter(fn ($item) => $item['needs_restaurant_rating'] || $item['needs_rider_rating'])
            ->values();

        return response()->json(['data' => $pending]);
    }

    /**
     * Share live tracking with a friend via app notification
     */
    public function shareTracking(Request $request, Order $order)
    {
        $validated = $request->validate([
            'recipient' => 'required|string', // email or phone
        ]);

        // Verify ownership: only the placing customer can share
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $recipientUser = User::where('email', $validated['recipient'])
            ->orWhere('phone', $validated['recipient'])
            ->first();

        if (! $recipientUser) {
            return response()->json(['error' => 'Recipient user not found on UtterLazy.'], 404);
        }

        if ($recipientUser->id === $request->user()->id) {
            return response()->json(['error' => 'You cannot share the tracking link with yourself.'], 422);
        }

        $fcm = app(FcmService::class);
        $shortId = substr($order->id, 0, 8);
        $title = 'Shared Order Tracking';
        $body = "{$request->user()->name} shared the live tracking link for Order #{$shortId} with you.";

        $fcm->sendToUser(
            $recipientUser,
            'customer',
            [
                'type' => 'shared_tracking',
                'order_id' => (string) $order->id,
                'screen' => 'order_tracking',
            ],
            $title,
            $body
        );

        return response()->json([
            'message' => 'Tracking link shared successfully via app notification!',
        ]);
    }

    public function reorderHistory(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->whereIn('status', ['delivered', 'completed'])
            ->with(['restaurant:id,name,logo_url', 'items.menuItem:id,name,price,image_url'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($order) => [
                'id' => $order->id,
                'restaurant' => $order->restaurant,
                'items_summary' => $order->items->map(fn ($i) => [
                    'name' => $i->menuItem->name ?? 'Unknown',
                    'quantity' => $i->quantity,
                    'price' => $i->unit_price,
                ]),
                'total' => $order->grand_total,
                'date' => $order->created_at->diffForHumans(),
                'can_reorder' => $order->items->every(fn ($i) => $i->menuItem && $i->menuItem->is_available),
            ]);

        return response()->json(['orders' => $orders]);
    }

    public function reorder(Request $request, Order $previousOrder)
    {
        if ($previousOrder->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Not your order.'], 403);
        }

        $previousOrder->load('items.menuItem');

        $cart = Cart::firstOrCreate([
            'user_id' => $request->user()->id,
            'restaurant_id' => $previousOrder->restaurant_id,
        ]);

        CartItem::where('cart_id', $cart->id)->delete();

        foreach ($previousOrder->items as $item) {
            if (! $item->menuItem || ! $item->menuItem->is_available) {
                continue;
            }
            CartItem::create([
                'cart_id' => $cart->id,
                'menu_item_id' => $item->menu_item_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
            ]);
        }

        $cart->load('items.menuItem');

        return response()->json([
            'message' => 'Previous order items added to cart. Review before checkout.',
            'cart' => $cart,
            'item_count' => $cart->items->sum('quantity'),
        ]);
    }

    public function uploadDeliveryPhoto(Request $request, Order $order)
    {
        $rider = $request->user();

        if ($order->rider_id !== $rider->id) {
            return response()->json(['error' => 'Not your assigned order.'], 403);
        }

        if ($order->status !== 'out_for_delivery') {
            return response()->json(['error' => 'Order is not out for delivery.'], 422);
        }

        $validated = $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        $orderId = substr($order->id, 0, 8);
        $path = $request->file('photo')->store("delivery-photos/{$orderId}", 'public');

        $order->update([
            'delivery_photo_path' => $path,
            'photo_taken_at' => now(),
        ]);

        return response()->json([
            'message' => 'Delivery photo saved!',
            'photo_url' => asset("storage/{$path}"),
        ]);
    }
}
