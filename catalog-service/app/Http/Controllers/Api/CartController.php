<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\MenuItem;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $cart = Cart::where('user_id', $request->user()->id)
            ->with(['items.menuItem', 'restaurant'])
            ->first();

        if (! $cart) {
            return response()->json(['cart' => null, 'items' => [], 'subtotal' => 0]);
        }

        $subtotal = $cart->items->sum(function ($item) {
            return $item->menuItem->price * $item->quantity;
        });

        return response()->json([
            'cart' => $cart,
            'items' => $cart->items,
            'subtotal' => $subtotal,
        ]);
    }

    public function addItem(Request $request)
    {
        $validated = $request->validate([
            'menu_item_id' => 'required|uuid',
            'quantity' => 'sometimes|integer|min:1|max:20',
        ]);

        $menuItem = MenuItem::findOrFail($validated['menu_item_id']);
        $user = $request->user();
        $quantity = $validated['quantity'] ?? 1;

        $cart = Cart::firstOrCreate(
            ['user_id' => $user->id],
            ['restaurant_id' => $menuItem->restaurant_id]
        );

        // Check if switching restaurants
        if ($cart->restaurant_id !== $menuItem->restaurant_id) {
            $cart->items()->delete();
            $cart->update(['restaurant_id' => $menuItem->restaurant_id]);
        }

        $cartItem = CartItem::updateOrCreate(
            ['cart_id' => $cart->id, 'menu_item_id' => $menuItem->id],
            ['quantity' => $quantity]
        );

        return response()->json([
            'message' => 'Item added to cart',
            'cart' => $cart->fresh()->load('items.menuItem'),
            'item_count' => $cart->fresh()->items->sum('quantity'),
        ]);
    }

    public function updateItem(Request $request, CartItem $cartItem)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:20',
        ]);

        $cartItem->update(['quantity' => $validated['quantity']]);

        $cart = $cartItem->cart->fresh()->load('items.menuItem');
        $subtotal = $cart->items->sum(function ($item) {
            return $item->menuItem->price * $item->quantity;
        });

        return response()->json([
            'message' => 'Cart updated',
            'cart' => $cart,
            'subtotal' => $subtotal,
            'item_count' => $cart->items->sum('quantity'),
        ]);
    }

    public function removeItem(CartItem $cartItem)
    {
        $cart = $cartItem->cart;
        $cartItem->delete();

        $cart->load('items.menuItem');
        if ($cart->items->isEmpty()) {
            $cart->delete();

            return response()->json(['message' => 'Cart is now empty', 'cart' => null, 'items' => [], 'subtotal' => 0]);
        }

        $subtotal = $cart->items->sum(function ($item) {
            return $item->menuItem->price * $item->quantity;
        });

        return response()->json([
            'message' => 'Item removed',
            'cart' => $cart,
            'subtotal' => $subtotal,
            'item_count' => $cart->items->sum('quantity'),
        ]);
    }

    public function clear(Request $request)
    {
        Cart::where('user_id', $request->user()->id)->delete();

        return response()->json(['message' => 'Cart cleared', 'cart' => null, 'items' => [], 'subtotal' => 0]);
    }
}
