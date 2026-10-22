<?php

namespace App\Domain\Conversation\States;

use App\Domain\Conversation\WaContext;
use App\Domain\Conversation\WaMessage;
use App\Domain\Conversation\WaReply;
use App\Services\CatalogClient;

class SponsoredState implements WaState
{
    /**
     * Button payload: sponsored:<placement_id>:<restaurant_id>
     * Charge the click once per inbound message, then open the menu.
     */
    public function handle(WaContext $context, WaMessage $message): WaReply
    {
        $parts = explode(':', (string) $message->buttonPayload);

        $placementId = $parts[1] ?? null;
        $restaurantId = $parts[2] ?? null;

        if (! $placementId || ! $restaurantId) {
            return WaReply::text($message->waId, 'Yeh offer ab available nahi hai. "near me" likh kar dobara dhoondo.');
        }

        $externalId = 'wa-'.sha1($message->waId.'|'.$placementId.'|'.($message->waMessageId ?? now()->timestamp));
        app(CatalogClient::class)->recordClick($placementId, $externalId);

        $restaurant = app(CatalogClient::class)->restaurant($restaurantId);
        $name = $restaurant['restaurant']['name'] ?? $restaurant['name'] ?? 'Restaurant';

        $context->set('restaurant_id', $restaurantId);
        $context->set('restaurant_name', $name);
        $context->transition(self::MENU);

        return WaReply::text(
            $message->waId,
            "🍽️ *{$name}* (Sponsored)\n\nMenu dekho aur item number bhejo:"
        );
    }
}
