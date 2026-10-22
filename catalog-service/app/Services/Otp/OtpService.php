<?php

namespace App\Services\Otp;

use App\Models\Otp;
use Carbon\Carbon;

/**
 * OTP issuance/verification with DB-backed throttles that do not depend on the
 * caller's IP (important for the WhatsApp service, which shares one egress IP).
 */
class OtpService
{
    public const TTL_MINUTES = 5;

    public const MAX_PER_10_MINUTES = 3;

    public const MAX_PER_DAY = 10;

    public const MAX_VERIFY_ATTEMPTS = 5;

    public function __construct(private readonly OtpSender $sender)
    {
    }

    /** @return array{status:string, expires_at?:Carbon} */
    public function issue(string $phone): array
    {
        if (! preg_match('/^[6-9][0-9]{9}$/', $phone)) {
            return ['status' => 'invalid_phone'];
        }

        $tenMinutes = Otp::where('phone', $phone)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->count();

        if ($tenMinutes >= self::MAX_PER_10_MINUTES) {
            return ['status' => 'throttled'];
        }

        $day = Otp::where('phone', $phone)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($day >= self::MAX_PER_DAY) {
            return ['status' => 'throttled'];
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otp = Otp::create([
            'phone' => $phone,
            'code' => $code,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'verified' => false,
            'attempts' => 0,
        ]);

        if (! $this->sender->send($phone, $code)) {
            // Do not count undelivered OTPs against the user's throttle.
            $otp->delete();

            return ['status' => 'delivery_failed'];
        }

        return ['status' => 'sent', 'expires_at' => $otp->expires_at];
    }

    /** @return array{status:string, otp?:Otp, attempts?:int} */
    public function verify(string $phone, string $code): array
    {
        $otp = Otp::where('phone', $phone)
            ->where('verified', false)
            ->orderByDesc('created_at')
            ->first();

        if (! $otp) {
            return ['status' => 'invalid'];
        }

        if ($otp->expires_at->isPast()) {
            return ['status' => 'expired'];
        }

        if ((int) $otp->attempts >= self::MAX_VERIFY_ATTEMPTS) {
            return ['status' => 'locked'];
        }

        if (! hash_equals((string) $otp->code, $code)) {
            $otp->increment('attempts');
            $attempts = (int) $otp->fresh()->attempts;

            return [
                'status' => $attempts >= self::MAX_VERIFY_ATTEMPTS ? 'locked' : 'invalid',
                'attempts' => $attempts,
            ];
        }

        $otp->update(['verified' => true]);

        return ['status' => 'ok', 'otp' => $otp->fresh()];
    }
}
