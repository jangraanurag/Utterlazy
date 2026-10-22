<?php

namespace App\Domain\Conversation\States;

use App\Domain\Conversation\WaContext;
use App\Domain\Conversation\WaMessage;
use App\Domain\Conversation\WaReply;
use App\Models\WaConsent;

class OptOutState implements WaState
{
    public function handle(WaContext $context, WaMessage $message): WaReply
    {
        $consent = WaConsent::firstOrNew(['wa_id' => $message->waId]);

        if ($message->buttonPayload === 'optout:yes') {
            $consent->marketing = false;
            $consent->save();

            $context->transition(self::SHARE_LOCATION);

            return WaReply::text($message->waId, "🔕 Sponsored suggestions band kar diye. Order updates aate rahenge.\n\nKya order karna hai?");
        }

        $consent->marketing = true;
        $consent->marketing_opted_at = $consent->marketing_opted_at ?? now();
        $consent->save();

        $context->transition(self::SHARE_LOCATION);

        return WaReply::text($message->waId, '👍 Sponsored suggestions chalu rahenge. Kya order karna hai?');
    }
}
