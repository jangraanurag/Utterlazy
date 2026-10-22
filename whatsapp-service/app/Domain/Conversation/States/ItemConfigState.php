<?php

namespace App\Domain\Conversation\States;

use App\Domain\Conversation\WaContext;
use App\Domain\Conversation\WaMessage;
use App\Domain\Conversation\WaReply;
use App\Models\WaCart;

class ItemConfigState implements WaState
{
    public function handle(WaContext $context, WaMessage $message): WaReply
    {
        $menu = $context->get('menu', []);
        $text = trim($message->text);

        // Format: "itemNo qty" e.g. "2 3"
        $parts = preg_split('/\s+/', $text);
        $itemNo = (int) ($parts[0] ?? 0);
        $qty = min((int) ($parts[1] ?? 1), 20);

        if ($itemNo < 1 || $itemNo > count($menu)) {
            return WaReply::text($message->waId, 'Menu ke andar ka item number bhejo (1-'.count($menu).'), quantity optional: "2 3".');
        }

        if ($qty < 1 || $qty > 20) {
            return WaReply::text($message->waId, 'Quantity 1 se 20 ke beech honi chahiye.');
        }

        $item = $menu[$itemNo - 1];
        $restaurantId = $context->get('restaurant_id');

        $cart = WaCart::firstOrNew(['wa_id' => $message->waId]);

        if ($cart->restaurant_id && $cart->restaurant_id !== $restaurantId) {
            $cart->lines = [];
        }

        $lines = $cart->lines ?? [];
        $lines[] = [
            'menu_item_id' => $item['id'],
            'quantity' => $qty,
            'unit_price' => (float) ($item['price'] ?? 0),
            'variant_id' => null,
            'add_on_item_ids' => [],
            'special_instructions' => null,
        ];

        if (count($lines) > 50) {
            return WaReply::text($message->waId, 'Cart mein max 50 items ho sakte hain. Checkout karo ya cart dekho.');
        }

        $cart->restaurant_id = $restaurantId;
        $cart->lines = $lines;
        $cart->save();

        $context->set('cart_lines', count($lines));
        $context->transition(self::CART);

        $total = collect($lines)->sum(fn ($l) => $l['unit_price'] * $l['quantity']);

        return WaReply::buttons(
            $message->waId,
            "✅ *{$item['name']}* x{$qty} added!\n\nCart: ".count($lines).' line(s), ₹'.number_format($total, 2)."\n\nAage kya?",
            [
                ['id' => 'cart:checkout', 'title' => '✅ Checkout'],
                ['id' => 'cart:more', 'title' => '➕ Aur Items'],
                ['id' => 'cart:cancel', 'title' => '🗑️ Cart Khaali'],
            ],
        );
    }
}
