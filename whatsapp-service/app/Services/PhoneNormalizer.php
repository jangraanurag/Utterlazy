<?php

namespace App\Services;

class PhoneNormalizer
{
    /**
     * WhatsApp wa_id / typed input → 10-digit Indian mobile, or null.
     * Accepts "9876543210", "+91 98765 43210", "919876543210".
     */
    public static function toLocal(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw);

        if ($digits === null || $digits === '') {
            return null;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) !== 10 || ! preg_match('/^[6-9][0-9]{9}$/', $digits)) {
            return null;
        }

        return $digits;
    }
}
