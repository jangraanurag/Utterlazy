<?php

namespace App\Domain\Conversation;

use App\Domain\Conversation\States\AddressPickState;
use App\Domain\Conversation\States\CartState;
use App\Domain\Conversation\States\CheckoutState;
use App\Domain\Conversation\States\HumanState;
use App\Domain\Conversation\States\ItemConfigState;
use App\Domain\Conversation\States\LanguageState;
use App\Domain\Conversation\States\MenuState;
use App\Domain\Conversation\States\NameSearchState;
use App\Domain\Conversation\States\NearMeState;
use App\Domain\Conversation\States\OptOutState;
use App\Domain\Conversation\States\PinSetupState;
use App\Domain\Conversation\States\PinVerifyState;
use App\Domain\Conversation\States\OtpState;
use App\Domain\Conversation\States\PhoneState;
use App\Domain\Conversation\States\PaymentState;
use App\Domain\Conversation\States\ShareLocationState;
use App\Domain\Conversation\States\SponsoredState;
use App\Domain\Conversation\States\TrackState;
use App\Domain\Conversation\States\WaState;
use App\Domain\Conversation\States\WelcomeState;
use App\Domain\Intent\IntentRouter;
use App\Models\WaSession;

class StateMachine
{
    private array $states;

    public function __construct(private readonly IntentRouter $router)
    {
        $this->states = [
            WaState::WELCOME => new WelcomeState,
            WaState::LANGUAGE => new LanguageState,
            WaState::PHONE => new PhoneState,
            WaState::OTP => new OtpState,
            WaState::PIN_SETUP => new PinSetupState,
            WaState::PIN_VERIFY => new PinVerifyState,
            WaState::SHARE_LOCATION => new ShareLocationState,
            WaState::ADDRESS_PICK => new AddressPickState,
            WaState::NEAR_ME => new NearMeState,
            WaState::NAME_SEARCH => new NameSearchState,
            WaState::MENU => new MenuState,
            WaState::ITEM_CONFIG => new ItemConfigState,
            WaState::CART => new CartState,
            WaState::CHECKOUT => new CheckoutState,
            WaState::PAYMENT => new PaymentState,
            WaState::TRACK => new TrackState,
            WaState::OPT_OUT => new OptOutState,
            WaState::HUMAN => new HumanState,
            WaState::SPONSORED => new SponsoredState,
        ];
    }

    public function handle(WaMessage $message): WaReply
    {
        $session = WaSession::firstOrCreate(
            ['wa_id' => $message->waId],
            [
                'state' => WaState::WELCOME,
                'context' => [],
                'expires_at' => now()->addMinutes((int) config('whatsapp.session_ttl_minutes', 30)),
            ],
        );

        // Expired session → restart the conversation, keep consent.
        if ($session->expires_at && $session->expires_at->isPast()) {
            $session->update([
                'state' => WaState::WELCOME,
                'context' => [],
                'expires_at' => now()->addMinutes((int) config('whatsapp.session_ttl_minutes', 30)),
            ]);
        }

        $context = new WaContext($session);

        // Sponsored card taps are stateless commands.
        if (str_starts_with((string) $message->buttonPayload, 'sponsored:')) {
            return (new SponsoredState)->handle($context, $message);
        }

        $state = $this->states[$session->state] ?? new WelcomeState;

        // Global command interception — these work from any state.
        $intent = $this->router->resolve($message->text, $message->buttonPayload)['intent'];

        if ($intent === IntentRouter::TRACK) {
            $state = new TrackState;
            $context->transition(WaState::TRACK);
        } elseif ($intent === IntentRouter::HUMAN) {
            $state = new HumanState;
            $context->transition(WaState::HUMAN);
        } elseif ($intent === IntentRouter::CANCEL_REQUEST) {
            // Cancellation is a support flow, never a direct order mutation.
            $state = new HumanState;
            $context->transition(WaState::HUMAN);
        }

        return $state->handle($context, $message);
    }
}
