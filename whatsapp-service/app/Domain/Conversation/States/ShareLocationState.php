<?php

namespace App\Domain\Conversation\States;

use App\Domain\Conversation\WaContext;
use App\Domain\Conversation\WaMessage;
use App\Domain\Conversation\WaReply;
use App\Services\CatalogClient;

class ShareLocationState implements WaState
{
    public function handle(WaContext $context, WaMessage $message): WaReply
    {
        if ($message->buttonPayload === 'nav:search') {
            $context->transition(self::NAME_SEARCH);

            return WaReply::text($message->waId, 'Restaurant ka naam likho (2-60 characters):');
        }

        if ($message->buttonPayload === 'nav:track') {
            $context->transition(self::TRACK);

            return WaReply::text($message->waId, 'Order ID bhejo, main status bata deta hoon:');
        }

        if ($message->buttonPayload === 'nav:opt_out') {
            $context->transition(self::OPT_OUT);

            return WaReply::buttons($message->waId, 'Sponsored suggestions band karne hain? Order updates band nahi honge.', [
                ['id' => 'optout:yes', 'title' => 'Haan, band karo'],
                ['id' => 'optout:no', 'title' => 'Nahi, chalu rakho'],
            ]);
        }

        if ($message->latitude === null || $message->longitude === null) {
            return WaReply::text($message->waId, "Location message nahi mila. 📎 → Location se apna live location share karo (lat -90..90, lng -180..180).");
        }

        $context->set('latitude', $message->latitude);
        $context->set('longitude', $message->longitude);
        $context->transition(self::NEAR_ME);

        $restaurants = app(CatalogClient::class)->nearMe($message->latitude, $message->longitude, 5);
        $data = $restaurants['data'] ?? $restaurants;

        if (empty($data)) {
            return WaReply::buttons($message->waId, 'Is area mein abhi koi open restaurant nahi mila. Naam se dhoondo ya location dobara bhejo.', [
                ['id' => 'nav:search', 'title' => '🔍 Naam Se Dhoondo'],
            ]);
        }

        $lines = ["🍽️ *Aapke paas ke restaurants (organic list):*"];
        foreach (array_values($data) as $i => $restaurant) {
            $name = $restaurant['name'] ?? 'Restaurant';
            $rating = isset($restaurant['rating']) ? " ⭐{$restaurant['rating']}" : '';
            $lines[] = ($i + 1).". {$name}{$rating}";
        }

        $context->set('list', $data);

        return WaReply::text($message->waId, implode("\n", $lines)."\n\nMenu ke liye number reply karo (1-5):");
    }
}
