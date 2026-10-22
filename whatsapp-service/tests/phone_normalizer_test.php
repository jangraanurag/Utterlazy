<?php

/**
 * Dependency-free checks for the WhatsApp channel helpers.
 * Run: php tests/phone_normalizer_test.php  (from whatsapp-service/)
 */

require __DIR__.'/../vendor/autoload.php';

use App\Domain\Conversation\StateMachine;
use App\Domain\Intent\IntentRouter;
use App\Services\PhoneNormalizer;
use App\Services\PinGuard;

$pass = 0;
$fail = 0;

function check(string $label, bool $ok): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  PASS  {$label}\n";
    } else {
        $fail++;
        echo "  FAIL  {$label}\n";
    }
}

echo "=== Phone normalization ===\n";
check('plain 10-digit', PhoneNormalizer::toLocal('9876543210') === '9876543210');
check('+91 with spaces', PhoneNormalizer::toLocal('+91 98765 43210') === '9876543210');
check('wa_id 919...', PhoneNormalizer::toLocal('919876543210') === '9876543210');
check('leading zero', PhoneNormalizer::toLocal('09876543210') === '9876543210');
check('too short rejected', PhoneNormalizer::toLocal('98765') === null);
check('starts 5 rejected', PhoneNormalizer::toLocal('5876543210') === null);
check('letters rejected', PhoneNormalizer::toLocal('abcdefghij') === null);
check('empty rejected', PhoneNormalizer::toLocal('') === null);

echo "=== PIN guard ===\n";
check('pin: 4 digits valid', PinGuard::isValid('4821'));
check('pin: short rejected', ! PinGuard::isValid('12'));
check('pin: letters rejected', ! PinGuard::isValid('12a4'));
$pinHash = PinGuard::hash('4821');
check('pin: hash verifies', PinGuard::verify('4821', $pinHash));
check('pin: wrong pin rejected', ! PinGuard::verify('0000', $pinHash));
check('pin: missing hash rejected', ! PinGuard::verify('4821', null));

echo "=== State machine wiring ===\n";
$machine = new StateMachine(new IntentRouter());
$ref = new ReflectionClass($machine);
$prop = $ref->getProperty('states');
$prop->setAccessible(true);
$states = $prop->getValue($machine);

check('PHONE state registered', isset($states['PHONE']));
check('OTP state registered', isset($states['OTP']));
check('PIN setup registered', isset($states['PIN_SETUP']));
check('PIN verify registered', isset($states['PIN_VERIFY']));
check('CHECKOUT still registered', isset($states['CHECKOUT']));
check('TRACK still registered', isset($states['TRACK']));
check('HUMAN still registered', isset($states['HUMAN']));

echo "\n=== Result: {$pass} PASS, {$fail} FAIL ===\n";
exit($fail > 0 ? 1 : 0);
