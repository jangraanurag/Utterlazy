<?php

namespace App\Domain\Conversation\States;

use App\Domain\Conversation\WaContext;
use App\Domain\Conversation\WaMessage;
use App\Domain\Conversation\WaReply;

class WelcomeState implements WaState
{
    public function handle(WaContext $context, WaMessage $message): WaReply
    {
        $context->transition(self::LANGUAGE);

        return WaReply::buttons(
            $message->waId,
            "Namaste! 🙏 UtterLazy pe swagat hai — app, web aur WhatsApp, teeno se order karo.\n\nPehle language chuno:",
            [
                ['id' => 'lang:hi', 'title' => 'हिंदी'],
                ['id' => 'lang:en', 'title' => 'English'],
            ],
        );
    }
}
