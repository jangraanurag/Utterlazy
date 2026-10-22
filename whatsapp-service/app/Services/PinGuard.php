<?php

namespace App\Services;

class PinGuard
{
    public static function isValid(string $pin): bool
    {
        return preg_match('/^[0-9]{4}$/', $pin) === 1;
    }

    public static function hash(string $pin): string
    {
        return password_hash($pin, PASSWORD_BCRYPT);
    }

    public static function verify(string $pin, ?string $hash): bool
    {
        return $hash !== null && $hash !== '' && password_verify($pin, $hash);
    }
}
