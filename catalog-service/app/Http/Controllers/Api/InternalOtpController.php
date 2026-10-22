<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Otp\CustomerTokenIssuer;
use App\Services\Otp\OtpService;
use Illuminate\Http\Request;

class InternalOtpController extends Controller
{
    public function request(Request $request, OtpService $otp)
    {
        $validated = $request->validate([
            'phone' => 'required|string|regex:/^[6-9][0-9]{9}$/',
        ]);

        $result = $otp->issue($validated['phone']);

        return match ($result['status']) {
            'sent' => response()->json([
                'status' => 'sent',
                'expires_in_seconds' => OtpService::TTL_MINUTES * 60,
            ]),
            'invalid_phone' => response()->json(['error' => 'Invalid phone number.'], 422),
            'throttled' => response()->json([
                'error' => 'Too many OTP requests for this number. Try again later.',
                'retry_after_seconds' => 600,
            ], 429),
            'delivery_failed' => response()->json([
                'error' => 'OTP delivery failed. Try again shortly.',
            ], 502),
            default => response()->json(['error' => 'OTP request failed.'], 500),
        };
    }

    public function verify(Request $request, OtpService $otp, CustomerTokenIssuer $issuer)
    {
        $validated = $request->validate([
            'phone' => 'required|string|regex:/^[6-9][0-9]{9}$/',
            'otp' => 'required|string|size:6',
        ]);

        $result = $otp->verify($validated['phone'], $validated['otp']);

        if ($result['status'] !== 'ok') {
            return match ($result['status']) {
                'locked' => response()->json([
                    'error' => 'Too many invalid attempts. Request a new OTP.',
                    'status' => 'locked',
                ], 429),
                'expired' => response()->json(['error' => 'OTP expired.', 'status' => 'expired'], 422),
                default => response()->json([
                    'error' => 'Invalid OTP.',
                    'status' => 'invalid',
                    'attempts' => $result['attempts'] ?? null,
                ], 422),
            };
        }

        $issued = $issuer->issue($validated['phone']);

        return response()->json([
            'status' => 'ok',
            'user' => $issued['user'],
            'token' => $issued['token'],
            'is_new_user' => $issued['is_new_user'],
        ]);
    }
}
