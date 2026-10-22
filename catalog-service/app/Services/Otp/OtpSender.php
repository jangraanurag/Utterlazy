<?php

namespace App\Services\Otp;

interface OtpSender
{
    /** Returns false when the provider rejects/fails delivery. */
    public function send(string $phone, string $code): bool;
}
