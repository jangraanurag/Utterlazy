<?php

namespace App\Domain\Conversation\States;

use App\Domain\Conversation\WaContext;
use App\Domain\Conversation\WaMessage;
use App\Domain\Conversation\WaReply;
use App\Services\CatalogClient;

class TrackState implements WaState
{
    public function handle(WaContext $context, WaMessage $message): WaReply
    {
        $userToken = app(\App\Services\WaTokenStore::class)->get($message->waId);

        if (! $userToken) {
            $context->set('after_link', 'track');
            $context->transition(self::PHONE);

            return WaReply::text(
                $message->waId,
                "Order track karne ke liye account link karo.\n10-digit mobile bhejo, ya YES likho agar isi WhatsApp number se link karna hai."
            );
        }

        $orderId = trim($message->text);
        if ($orderId === '' || $orderId === 'track') {
            $orderId = $context->get('order_id');
        }

        if (! $orderId) {
            return WaReply::text($message->waId, 'Order ID bhejo:');
        }

        $result = app(CatalogClient::class)->order($userToken, $orderId);

        if ($result['status'] !== 200) {
            return WaReply::text($message->waId, 'Order nahi mila ya ye order aapka nahi hai.');
        }

        $order = $result['body']['order'] ?? [];
        $status = $order['status'] ?? 'pending';
        $otp = $order['delivery_otp'] ?? null;

        $text = "🛵 *Order Status:* {$status}";
        if ($otp) {
            $text .= "\n🔐 Delivery OTP: {$otp}";
        }

        return WaReply::text($message->waId, $text);
    }
}
