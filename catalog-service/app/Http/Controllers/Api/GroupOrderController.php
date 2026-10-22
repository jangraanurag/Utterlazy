<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GroupOrder;
use App\Models\GroupOrderItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class GroupOrderController extends Controller
{
    /**
     * Create a group order (host creates, friends join)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'restaurant_id' => 'required|uuid',
            'scheduled_at' => 'sometimes|date|after:now',
        ]);

        $inviteCode = strtoupper(bin2hex(random_bytes(4)));

        $groupOrder = GroupOrder::create([
            'host_user_id' => $request->user()->id,
            'restaurant_id' => $validated['restaurant_id'],
            'invite_code' => $inviteCode,
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'status' => 'collecting',
        ]);

        return response()->json([
            'message' => 'Group order created',
            'group_order' => $groupOrder,
            'invite_code' => $inviteCode,
            'join_url' => "/group/{$inviteCode}",
        ], 201);
    }

    /**
     * Join a group order via invite code
     */
    public function join(Request $request, $inviteCode)
    {
        $groupOrder = GroupOrder::where('invite_code', $inviteCode)
            ->where('status', 'collecting')
            ->firstOrFail();

        // Add user as participant (or just allow them to add items)
        $groupOrder->increment('participant_count');

        return response()->json([
            'group_order' => $groupOrder->load('restaurant:id,name'),
            'message' => 'Joined group order',
        ]);
    }

    /**
     * Add item to group order
     */
    public function addItem(Request $request, GroupOrder $groupOrder)
    {
        if ($groupOrder->status !== 'collecting') {
            return response()->json(['error' => 'Group order is no longer accepting items'], 422);
        }

        $validated = $request->validate([
            'menu_item_id' => 'required|uuid',
            'quantity' => 'required|integer|min:1|max:20',
        ]);

        $item = GroupOrderItem::create([
            'group_order_id' => $groupOrder->id,
            'user_id' => $request->user()->id,
            'menu_item_id' => $validated['menu_item_id'],
            'quantity' => $validated['quantity'],
        ]);

        return response()->json([
            'message' => 'Item added',
            'item' => $item->load('menuItem:id,name,price'),
        ]);
    }

    /**
     * Get all items in group order
     */
    public function items(GroupOrder $groupOrder)
    {
        $items = GroupOrderItem::where('group_order_id', $groupOrder->id)
            ->with('user:id,name')
            ->with('menuItem:id,name,price')
            ->get();

        $total = $items->sum(function ($item) {
            return $item->menuItem->price * $item->quantity;
        });

        return response()->json([
            'group_order' => $groupOrder,
            'items' => $items,
            'total' => $total,
            'item_count' => $items->sum('quantity'),
        ]);
    }

    /**
     * Host finalizes group order — creates individual orders
     */
    public function finalize(Request $request, GroupOrder $groupOrder)
    {
        if ($groupOrder->host_user_id !== $request->user()->id) {
            return response()->json(['error' => 'Only the host can finalize'], 403);
        }

        $groupOrder->update(['status' => 'finalized']);

        // Group items by user and create individual orders
        $itemsByUser = GroupOrderItem::where('group_order_id', $groupOrder->id)
            ->get()
            ->groupBy('user_id');

        $ordersCreated = [];
        foreach ($itemsByUser as $userId => $items) {
            $order = Order::create([
                'user_id' => $userId,
                'restaurant_id' => $groupOrder->restaurant_id,
                'status' => 'pending',
                'payment_mode' => 'prepaid',
                'subtotal' => 0,
                'tax_amount' => 0,
                'grand_total' => 0,
            ]);

            foreach ($items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $item->menu_item_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->menuItem->price,
                    'total_price' => $item->menuItem->price * $item->quantity,
                ]);
            }

            $ordersCreated[] = $order->id;
        }

        return response()->json([
            'message' => 'Group order finalized',
            'orders_created' => count($ordersCreated),
            'order_ids' => $ordersCreated,
        ]);
    }

    /**
     * Cancel group order
     */
    public function cancel(Request $request, GroupOrder $groupOrder)
    {
        if ($groupOrder->host_user_id !== $request->user()->id) {
            return response()->json(['error' => 'Only the host can cancel'], 403);
        }

        $groupOrder->update(['status' => 'cancelled']);
        GroupOrderItem::where('group_order_id', $groupOrder->id)->delete();

        return response()->json(['message' => 'Group order cancelled']);
    }
}
