<?php

namespace App\Domain\Conversation\States;

use App\Domain\Conversation\WaContext;
use App\Domain\Conversation\WaMessage;
use App\Domain\Conversation\WaReply;
use App\Models\WaContact;

class LanguageState implements WaState
{
    public function handle(WaContext $context, WaMessage $message): WaReply
    {
        $lang = str_starts_with($message->buttonPayload ?? '', 'lang:hi') ? 'hi' : 'en';

        WaContact::updateOrCreate(
            ['wa_id' => $message->waId],
            ['language' => $lang, 'last_seen' => now()],
        );

        $context->transition(self::SHARE_LOCATION);

        $text = $lang === 'hi'
            ? "Khaane ka mood? 🍽️\n\n📍 Apna location share karo (WhatsApp ka 📎 → Location), ya apna saved address number se chuno."
            : "In the mood for food? 🍽️\n\n📍 Share your location (📎 → Location), or pick a saved address by number.";

        return WaReply::buttons($message->waId, $text, [
            ['id' => 'nav:near_me', 'title' => $lang === 'hi' ? '📍 Location Share' : '📍 Share Location'],
            ['id' => 'nav:search', 'title' => $lang === 'hi' ? '🔍 Naam Se Dhoondo' : '🔍 Search by Name'],
            ['id' => 'nav:track', 'title' => $lang === 'hi' ? '🛵 Order Track' : '🛵 Track Order'],
            ['id' => 'nav:opt_out', 'title' => $lang === 'hi' ? '🔕 Sponsored Band' : '🔕 No Sponsored'],
        ]);
    }
}
