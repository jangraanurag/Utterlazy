<?php

namespace App\Services\Otp;

use App\Services\SmsService;

class SmsOtpSender implements OtpSender
{
    public function __construct(private readonly SmsService $sms)
    {
    }

    public function send(string $phone, string $code): bool
    {
        return (bool) $this->sms->sendOtp($phone, $code);
    }
}
