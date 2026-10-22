<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class VoiceAssistantService
{
    protected GeminiService $gemini;

    public function __construct(GeminiService $gemini)
    {
        $this->gemini = $gemini;
    }

    /**
     * Parse natural language from any assistant into a structured order intent.
     */
    public function parseIntent(string $text, array $context = []): array
    {
        $userContext = '';
        if (! empty($context['user_id'])) {
            $user = User::find($context['user_id']);
            if ($user) {
                $recentOrder = Order::where('user_id', $user->id)
                    ->whereIn('status', ['delivered', 'completed'])
                    ->latest()
                    ->first();
                if ($recentOrder) {
                    $userContext = "User's last ordered from: {$recentOrder->restaurant?->name}. ";
                }
            }
        }

        $prompt = <<<PROMPT
You are UtterLazy Food Assistant, a voice ordering system for a food delivery app in India.
Parse the user's speech-to-text into a structured order JSON.

{$userContext}
User said: "{$text}"

Respond with ONLY valid JSON (no markdown, no explanation):
{
  "action": "search|order|track|reorder|cancel|support|unknown",
  "restaurant_name": "specific restaurant name or null",
  "food_items": [{"name": "item name", "quantity": 1}],
  "order_id": "if tracking/cancelling an order, the ID or null",
  "preferences": {"is_veg": true|false|null, "spice_level": "low|medium|high|null", "budget": number|null},
  "delivery_address": "address if mentioned or null",
  "payment_method": "cod|online|null",
  "response_text": "a short friendly confirmation/reply in the user's language"
}

Examples:
- "order 2 butter chicken and 3 naan from darbar" → {"action":"order","restaurant_name":"darbar","food_items":[{"name":"butter chicken","quantity":2},{"name":"naan","quantity":3}],"order_id":null,...}
- "mujhe chicken biryani chahiye" → {"action":"search","food_items":[{"name":"chicken biryani","quantity":1}],...}
- "track my order" → {"action":"track","order_id":"last","restaurant_name":null,...}
- "repeat my last order" → {"action":"reorder","order_id":"last",...}
- "mujhe order cancel karna hai" → {"action":"cancel","order_id":"last",...}
PROMPT;

        $response = $this->gemini->callGemini($prompt, '{"action":"unknown","food_items":[],"response_text":"Sorry, I did not understand. Please try again."}');
        $intent = json_decode($response, true);

        if (! is_array($intent)) {
            return [
                'action' => 'unknown',
                'food_items' => [],
                'response_text' => 'Sorry, I did not understand. Please try again.',
            ];
        }

        return $intent;
    }

    /**
     * Execute the parsed intent and return a response.
     */
    public function executeIntent(array $intent, ?User $user = null, array $location = []): array
    {
        return match ($intent['action']) {
            'search' => $this->handleSearch($intent, $location),
            'order' => $this->handleOrder($intent, $user, $location),
            'reorder' => $this->handleReorder($intent, $user),
            'track' => $this->handleTrack($intent, $user),
            'cancel' => $this->handleCancel($intent, $user),
            default => [
                'success' => false,
                'response_text' => $intent['response_text'] ?? 'How can I help you? You can say "order biryani" or "track my order".',
                'data' => null,
            ],
        };
    }

    protected function handleSearch(array $intent, array $location): array
    {
        $searchTerm = '';
        foreach ($intent['food_items'] ?? [] as $item) {
            $searchTerm .= ($item['name'] ?? '').' ';
        }
        $searchTerm = trim($searchTerm ?: $intent['restaurant_name'] ?? '');

        if (empty($searchTerm)) {
            return [
                'success' => false,
                'response_text' => 'What would you like to order? For example, "find chicken biryani".',
                'data' => null,
            ];
        }

        $query = Restaurant::where('is_active', true);
        $query->where(function ($q) use ($searchTerm) {
            $q->where('name', 'LIKE', "%{$searchTerm}%")
                ->orWhere('description', 'LIKE', "%{$searchTerm}%");
        });

        if (! empty($location['lat']) && ! empty($location['lng'])) {
            $radius = 0.1;
            $query->whereBetween('latitude', [$location['lat'] - $radius, $location['lat'] + $radius])
                ->whereBetween('longitude', [$location['lng'] - $radius, $location['lng'] + $radius]);
        }

        $restaurants = $query->limit(5)->get();
        $menuItems = MenuItem::where('is_available', true)
            ->where('name', 'LIKE', "%{$searchTerm}%")
            ->with('restaurant:id,name')
            ->limit(10)
            ->get();

        if ($restaurants->isEmpty() && $menuItems->isEmpty()) {
            return [
                'success' => false,
                'response_text' => "Sorry, I couldn't find {$searchTerm} near you. Try something else like pizza or biryani.",
                'data' => null,
            ];
        }

        $names = $restaurants->pluck('name')->merge($menuItems->pluck('name'))->unique()->take(3)->values();
        $list = implode(', ', $names->toArray());

        return [
            'success' => true,
            'response_text' => "I found {$names->count()} options. {$list}. Which one would you like to order from? Say the name or number.",
            'data' => [
                'restaurants' => $restaurants,
                'menu_items' => $menuItems,
            ],
        ];
    }

    protected function handleOrder(array $intent, ?User $user, array $location): array
    {
        if (! $user) {
            return [
                'success' => false,
                'response_text' => 'Please sign in to your UtterLazy account to place an order. Say "sign in" to get started.',
                'data' => null,
            ];
        }

        $restaurantName = $intent['restaurant_name'] ?? null;
        if (! $restaurantName) {
            return [
                'success' => false,
                'response_text' => 'Which restaurant would you like to order from? Say the restaurant name.',
                'data' => null,
            ];
        }

        $restaurant = Restaurant::where('is_active', true)
            ->where('name', 'LIKE', "%{$restaurantName}%")
            ->first();

        if (! $restaurant) {
            return [
                'success' => false,
                'response_text' => "Sorry, I couldn't find {$restaurantName}. Try another restaurant.",
                'data' => null,
            ];
        }

        $foodItems = $intent['food_items'] ?? [];
        if (empty($foodItems)) {
            return [
                'success' => true,
                'response_text' => "I found {$restaurant->name}. What would you like to order from there? You can say things like '2 butter chicken and 3 naan'.",
                'data' => ['restaurant' => $restaurant],
            ];
        }

        $cart = Cart::firstOrCreate([
            'user_id' => $user->id,
            'restaurant_id' => $restaurant->id,
        ]);

        CartItem::where('cart_id', $cart->id)->delete();

        $addedItems = [];
        foreach ($foodItems as $fi) {
            $name = $fi['name'] ?? '';
            $quantity = max(1, (int) ($fi['quantity'] ?? 1));

            $menuItem = MenuItem::where('restaurant_id', $restaurant->id)
                ->where('is_available', true)
                ->where('name', 'LIKE', "%{$name}%")
                ->first();

            if ($menuItem) {
                CartItem::create([
                    'cart_id' => $cart->id,
                    'menu_item_id' => $menuItem->id,
                    'quantity' => $quantity,
                    'unit_price' => $menuItem->price,
                ]);
                $addedItems[] = "{$quantity}× {$menuItem->name}";
            }
        }

        if (empty($addedItems)) {
            return [
                'success' => false,
                'response_text' => "Sorry, I couldn't find those items at {$restaurant->name}. Please try different items.",
                'data' => null,
            ];
        }

        $cartTotal = CartItem::where('cart_id', $cart->id)->sum(DB::raw('quantity * unit_price'));
        $itemList = implode(', ', $addedItems);

        return [
            'success' => true,
            'response_text' => "I've added {$itemList} to your cart from {$restaurant->name}. Total is ₹{$cartTotal}. Would you like to checkout? Say 'checkout' or 'place order'.",
            'data' => [
                'cart_id' => $cart->id,
                'item_count' => count($addedItems),
                'total' => $cartTotal,
            ],
        ];
    }

    protected function handleReorder(array $intent, ?User $user): array
    {
        if (! $user) {
            return ['success' => false, 'response_text' => 'Please sign in first.', 'data' => null];
        }

        $lastOrder = Order::where('user_id', $user->id)
            ->whereIn('status', ['delivered', 'completed'])
            ->latest()
            ->first();

        if (! $lastOrder) {
            return [
                'success' => false,
                'response_text' => "You don't have any previous orders to repeat. Would you like to search for a restaurant instead?",
                'data' => null,
            ];
        }

        $cart = Cart::firstOrCreate([
            'user_id' => $user->id,
            'restaurant_id' => $lastOrder->restaurant_id,
        ]);

        CartItem::where('cart_id', $cart->id)->delete();

        $lastOrder->load('items.menuItem');
        foreach ($lastOrder->items as $item) {
            if ($item->menuItem && $item->menuItem->is_available) {
                CartItem::create([
                    'cart_id' => $cart->id,
                    'menu_item_id' => $item->menu_item_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                ]);
            }
        }

        return [
            'success' => true,
            'response_text' => "I've repeated your last order from {$lastOrder->restaurant->name}. Say 'checkout' to place it.",
            'data' => ['order_id' => $lastOrder->id],
        ];
    }

    protected function handleTrack(array $intent, ?User $user): array
    {
        if (! $user) {
            return ['success' => false, 'response_text' => 'Please sign in first.', 'data' => null];
        }

        $orderId = $intent['order_id'] ?? null;
        $order = null;

        if ($orderId && $orderId !== 'last') {
            $order = Order::where('user_id', $user->id)->find($orderId);
        } else {
            $order = Order::where('user_id', $user->id)
                ->whereNotIn('status', ['delivered', 'completed', 'cancelled'])
                ->latest()
                ->first();
        }

        if (! $order) {
            return [
                'success' => false,
                'response_text' => "You don't have any active orders to track.",
                'data' => null,
            ];
        }

        $statusLabels = [
            'pending' => 'waiting for the restaurant to confirm',
            'confirmed' => 'confirmed by the restaurant',
            'preparing' => 'being prepared in the kitchen',
            'ready_for_pickup' => 'ready for pickup',
            'out_for_delivery' => 'on its way to you',
            'delivered' => 'delivered',
        ];

        $statusText = $statusLabels[$order->status] ?? $order->status;

        return [
            'success' => true,
            'response_text' => "Your order from {$order->restaurant->name} is {$statusText}. Grand total is ₹{$order->grand_total}.",
            'data' => [
                'order_id' => $order->id,
                'status' => $order->status,
                'restaurant' => $order->restaurant->name,
                'total' => $order->grand_total,
            ],
        ];
    }

    protected function handleCancel(array $intent, ?User $user): array
    {
        if (! $user) {
            return ['success' => false, 'response_text' => 'Please sign in first.', 'data' => null];
        }

        $order = Order::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->latest()
            ->first();

        if (! $order) {
            return [
                'success' => false,
                'response_text' => "You don't have any orders that can be cancelled.",
                'data' => null,
            ];
        }

        $order->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        return [
            'success' => true,
            'response_text' => "Your order from {$order->restaurant->name} has been cancelled. You can say 'order' to place a new one.",
            'data' => ['order_id' => $order->id],
        ];
    }
}
