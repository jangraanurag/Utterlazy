<?php

namespace App\Domain\Conversation\States;

use App\Domain\Conversation\WaContext;
use App\Domain\Conversation\WaMessage;
use App\Domain\Conversation\WaReply;

class PaymentState implements WaState
{
    public function handle(WaContext $context, WaMessage $message): WaReply
    {
        $text = mb_strtolower(trim($message->text));

        if ($text === 'track' || $text === 'status') {
            $context->transition(self::TRACK);

            return WaReply::text($message->waId, 'Order ID bhejo:');
        }

        $orderId = $context->get('order_id');

        return WaReply::buttons(
            $message->waId,
            "Order {$orderId} placed hai. ✅\n\nPayment ya tracking ke liye:",
            [
                ['id' => 'nav:track', 'title' => '🛵 Track Order'],
                ['id' => 'nav:near_me', 'title' => '🍽️ Aur Order Karein'],
            ],
        );
    }
}
