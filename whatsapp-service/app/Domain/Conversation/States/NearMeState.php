<?php

namespace App\Domain\Conversation\States;

use App\Domain\Conversation\WaContext;
use App\Domain\Conversation\WaMessage;
use App\Domain\Conversation\WaReply;
use App\Services\CatalogClient;

class NearMeState implements WaState
{
    public function handle(WaContext $context, WaMessage $message): WaReply
    {
        $pick = (int) trim($message->text);

        $list = $context->get('list', []);

        if ($pick < 1 || $pick > count($list)) {
            return WaReply::text($message->waId, 'List ke andar ka number bhejo (1-'.count($list).').');
        }

        $restaurant = $list[$pick - 1];

        // Sponsored selection → charge CPC once per selection action.
        if (! empty($restaurant['placement_id'])) {
            $externalId = 'wa-'.sha1($message->waId.'|'.$restaurant['placement_id'].'|'.($message->waMessageId ?? now()->timestamp));
            app(CatalogClient::class)->recordClick($restaurant['placement_id'], $externalId);
        }

        $context->set('restaurant_id', $restaurant['id']);
        $context->set('restaurant_name', $restaurant['name'] ?? 'Restaurant');
        $context->transition(self::MENU);

        return WaReply::text(
            $message->waId,
            "🍽️ *{$context->get('restaurant_name')}*\n\nMenu ke liye item number bhejo. Abhi ke liye in-app menu use karo ya item ka naam likho — main check karta hoon."
        );
    }
}
