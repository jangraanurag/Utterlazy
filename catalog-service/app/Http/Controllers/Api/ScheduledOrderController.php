<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ScheduledOrder;
use Illuminate\Http\Request;

class ScheduledOrderController extends Controller
{
    /**
     * Create a scheduled order
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'restaurant_id' => 'required|uuid',
            'items' => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|uuid',
            'items.*.quantity' => 'required|integer|min:1',
            'scheduled_at' => 'required|date|after:now',
            'address_id' => 'required|integer',
            'payment_mode' => 'sometimes|in:prepaid,cod',
            'coupon_code' => 'sometimes|string',
            'notes' => 'sometimes|string|max:500',
        ]);

        $scheduledOrder = ScheduledOrder::create([
            'user_id' => $request->user()->id,
            'restaurant_id' => $validated['restaurant_id'],
            'items' => $validated['items'],
            'address_id' => $validated['address_id'],
            'scheduled_at' => $validated['scheduled_at'],
            'payment_mode' => $validated['payment_mode'] ?? 'prepaid',
            'coupon_code' => $validated['coupon_code'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => 'scheduled',
        ]);

        return response()->json([
            'message' => 'Order scheduled successfully',
            'scheduled_order' => $scheduledOrder,
        ], 201);
    }

    /**
     * List user's scheduled orders
     */
    public function index(Request $request)
    {
        $orders = ScheduledOrder::where('user_id', $request->user()->id)
            ->with('restaurant:id,name')
            ->latest()
            ->paginate(20);

        return response()->json($orders);
    }

    /**
     * Cancel a scheduled order
     */
    public function cancel(Request $request, ScheduledOrder $scheduledOrder)
    {
        if ($scheduledOrder->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        if ($scheduledOrder->status !== 'scheduled') {
            return response()->json(['error' => 'Cannot cancel. Order is already '.$scheduledOrder->status], 422);
        }

        $scheduledOrder->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Scheduled order cancelled']);
    }

    /**
     * Admin/vendor: process scheduled orders that are due
     * Called by scheduler every minute
     */
    public function processDue()
    {
        $dueOrders = ScheduledOrder::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($dueOrders as $scheduledOrder) {
            // Convert scheduled order to actual order
            $order = Order::create([
                'user_id' => $scheduledOrder->user_id,
                'restaurant_id' => $scheduledOrder->restaurant_id,
                'status' => 'pending',
                'payment_mode' => $scheduledOrder->payment_mode,
                'subtotal' => 0,
                'tax_amount' => 0,
                'grand_total' => 0,
            ]);

            foreach ($scheduledOrder->items as $item) {
                $menuItem = MenuItem::find($item['menu_item_id']);
                if ($menuItem) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'menu_item_id' => $menuItem->id,
                        'quantity' => $item['quantity'],
                        'unit_price' => $menuItem->price,
                        'total_price' => $menuItem->price * $item['quantity'],
                    ]);
                }
            }

            $scheduledOrder->update([
                'status' => 'placed',
                'order_id' => $order->id,
            ]);
        }

        return $dueOrders->count().' scheduled order(s) processed.';
    }
}
