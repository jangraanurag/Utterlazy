<?php

namespace App\Domain\Conversation\States;

use App\Domain\Conversation\WaContext;
use App\Domain\Conversation\WaMessage;
use App\Domain\Conversation\WaReply;
use App\Models\WaCart;

class CartState implements WaState
{
    public function handle(WaContext $context, WaMessage $message): WaReply
    {
        $cart = WaCart::where('wa_id', $message->waId)->first();

        if ($message->buttonPayload === 'cart:cancel') {
            $cart?->delete();
            $context->transition(self::SHARE_LOCATION);

            return WaReply::text($message->waId, 'Cart khaali kar diya. Kya order karna hai?');
        }

        if ($message->buttonPayload === 'cart:more') {
            $context->transition(self::MENU);

            return WaReply::text($message->waId, "Menu ke liye item number bhejo:");
        }

        if (! $cart || empty($cart->lines)) {
            $context->transition(self::SHARE_LOCATION);

            return WaReply::text($message->waId, 'Cart khaali hai. Pehle kuch add karo.');
        }

        $total = collect($cart->lines)->sum(fn ($l) => $l['unit_price'] * $l['quantity']);

        $context->set('cart_total', $total);
        $context->transition(self::CHECKOUT);

        return WaReply::buttons(
            $message->waId,
            "🛒 *Cart Summary*\n".count($cart->lines).' line(s) — ₹'.number_format($total, 2)."\n\nPayment kaise karna hai?",
            [
                ['id' => 'pay:cod', 'title' => '💵 Cash on Delivery'],
                ['id' => 'pay:prepaid', 'title' => '📲 Pay Online'],
            ],
        );
    }
}
