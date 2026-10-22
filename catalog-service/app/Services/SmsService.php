<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected $apiKey;

    protected $senderId;

    public function __construct()
    {
        $this->apiKey = config('services.fast2sms.key');
    }

    /**
     * Send OTP via Fast2Sms
     */
    public function sendOtp($phone, $otp)
    {
        try {
            $response = Http::withHeaders([
                'authorization' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://www.fast2sms.com/dev/bulkV2', [
                'route' => 'otp',
                'variables_values' => $otp,
                'numbers' => $phone,
            ]);

            if ($response->successful()) {
                Log::info("OTP sent successfully to {$phone}");

                return true;
            }

            Log::error('Fast2Sms Error: '.$response->body());

            return false;
        } catch (\Exception $e) {
            Log::error('Fast2Sms Exception: '.$e->getMessage());

            return false;
        }
    }
}
