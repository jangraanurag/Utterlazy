<?php

namespace App\Domain\Conversation\States;

use App\Domain\Conversation\WaContext;
use App\Domain\Conversation\WaMessage;
use App\Domain\Conversation\WaReply;

interface WaState
{
    public const WELCOME = 'WELCOME';
    public const LANGUAGE = 'LANGUAGE';
    public const PHONE = 'PHONE';
    public const OTP = 'OTP';
    public const PIN_SETUP = 'PIN_SETUP';
    public const PIN_VERIFY = 'PIN_VERIFY';
    public const SHARE_LOCATION = 'SHARE_LOCATION';
    public const ADDRESS_PICK = 'ADDRESS_PICK';
    public const NEAR_ME = 'NEAR_ME';
    public const NAME_SEARCH = 'NAME_SEARCH';
    public const MENU = 'MENU';
    public const ITEM_CONFIG = 'ITEM_CONFIG';
    public const CART = 'CART';
    public const CHECKOUT = 'CHECKOUT';
    public const PAYMENT = 'PAYMENT';
    public const TRACK = 'TRACK';
    public const OPT_OUT = 'OPT_OUT';
    public const HUMAN = 'HUMAN';
    public const SPONSORED = 'SPONSORED';

    public function handle(WaContext $context, WaMessage $message): WaReply;
}
