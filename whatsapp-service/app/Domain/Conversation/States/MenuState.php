<?php

namespace App\Domain\Conversation\States;

use App\Domain\Conversation\WaContext;
use App\Domain\Conversation\WaMessage;
use App\Domain\Conversation\WaReply;
use App\Models\WaCart;
use App\Services\CatalogClient;

class MenuState implements WaState
{
    public function handle(WaContext $context, WaMessage $message): WaReply
    {
        $restaurantId = $context->get('restaurant_id');

        if (! $restaurantId) {
            $context->transition(self::SHARE_LOCATION);

            return WaReply::text($message->waId, 'Pehle restaurant chuno — location share karo ya naam search karo.');
        }

        $menu = app(CatalogClient::class)->menuItems($restaurantId, 10);
        $data = $menu['data'] ?? $menu;

        if (empty($data)) {
            return WaReply::text($message->waId, "Is restaurant ka menu abhi available nahi hai. Koi aur restaurant try karo.");
        }

        $lines = ["📋 *{$context->get('restaurant_name')} — Menu:*"];
        foreach (array_values($data) as $i => $item) {
            $name = $item['name'] ?? 'Item';
            $price = isset($item['price']) ? "₹{$item['price']}" : '';
            $lines[] = ($i + 1).". {$name} — {$price}";
        }

        $context->set('menu', $data);
        $context->transition(self::ITEM_CONFIG);

        return WaReply::text($message->waId, implode("\n", $lines)."\n\nAdd karne ke liye number bhejo:");
    }
}
