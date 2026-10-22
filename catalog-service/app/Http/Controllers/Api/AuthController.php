<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Otp;
use App\Models\User;
use App\Services\GeminiService;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'khaalo_coins' => 100, // Welcome bonus
        ]);

        $token = $user->createToken('utterlazy-customer')->plainTextToken;

        return response()->json([
            'message' => 'Welcome to UtterLazy! You earned 100 welcome coins.',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Revoke old tokens for this device
        $user->tokens()->delete();
        $token = $user->createToken('utterlazy-customer')->plainTextToken;

        return response()->json([
            'message' => 'Welcome back!',
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function requestOtp(Request $request, SmsService $smsService)
    {
        $validated = $request->validate([
            'phone' => 'required|string|regex:/^[0-9]{10}$/',
        ]);

        $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Save OTP
        Otp::create([
            'phone' => $validated['phone'],
            'code' => $otpCode,
            'expires_at' => Carbon::now()->addMinutes(5),
        ]);

        // Send OTP
        $sent = $smsService->sendOtp($validated['phone'], $otpCode);

        if ($sent) {
            return response()->json([
                'message' => 'OTP sent successfully.',
                'phone' => $validated['phone'],
                // 'otp' => $otpCode, // Only for development if SMS not working
            ]);
        }

        return response()->json(['error' => 'Failed to send OTP. Please try again.'], 500);
    }

    public function verifyOtp(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string|regex:/^[0-9]{10}$/',
            'otp' => 'required|string|size:6',
        ]);

        $otpRecord = Otp::where('phone', $validated['phone'])
            ->where('code', $validated['otp'])
            ->where('expires_at', '>', Carbon::now())
            ->where('verified', false)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $otpRecord) {
            return response()->json(['error' => 'Invalid or expired OTP.'], 422);
        }

        $otpRecord->update(['verified' => true]);

        // Find or create user
        $user = User::where('phone', $validated['phone'])->first();

        if (! $user) {
            // Shadow profile for new users - they will need to update name/email later
            $user = User::create([
                'name' => 'Guest User',
                'email' => $validated['phone'].'@utterlazy.com', // Placeholder
                'phone' => $validated['phone'],
                'password' => Hash::make(Str::random(16)),
                'khaalo_coins' => 50, // Smaller bonus for OTP-only signup
            ]);
            $isNewUser = true;
        } else {
            $isNewUser = false;
        }

        $token = $user->createToken('utterlazy-customer')->plainTextToken;

        return response()->json([
            'message' => $isNewUser ? 'Account created via OTP!' : 'Welcome back!',
            'user' => $user,
            'token' => $token,
            'is_new_user' => $isNewUser,
        ]);
    }

    public function me(Request $request, GeminiService $geminiService)
    {
        $user = $request->user();

        $recentOrders = $user->orders()
            ->with('items.menuItem', 'restaurant')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        try {
            $greeting = $geminiService->generateGreeting($user->name, ['weather' => 'sunny']);
        } catch (\Throwable $e) {
            $greeting = "Welcome back, {$user->name}!";
        }

        return response()->json([
            'user' => $user,
            'greeting' => $greeting,
            'recent_orders' => $recentOrders,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,'.$user->id,
            'phone' => 'nullable|string|max:20',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user->fresh(),
        ]);
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($validated['new_password'])]);

        return response()->json(['message' => 'Password changed successfully.']);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function adminLogin(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_admin) {
            return response()->json(['error' => 'Unauthorized — admin access only'], 403);
        }

        // Don't revoke existing tokens — use a separate token with admin ability
        $token = $user->createToken('utterlazy-admin', ['admin'])->plainTextToken;

        return response()->json([
            'message' => 'Admin login successful',
            'user' => $user,
            'admin_role' => $user->admin_role ?? 'super_admin',
            'token' => $token,
        ]);
    }

    // ═══════════════════════════════════════
    //  PASSWORD RESET VIA EMAIL OTP
    // ═══════════════════════════════════════

    /**
     * Request a password reset OTP sent to the user's email.
     */
    public function requestPasswordReset(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $validated['email'])->first();

        // Generate 6-digit OTP
        $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store OTP linked to email (phone column stores email for password reset OTPs)
        Otp::create([
            'phone' => $validated['email'], // reusing phone column for email OTPs
            'code' => $otpCode,
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        // In production, send email via mail service
        // For now, return OTP in response for development
        Log::info("Password reset OTP for {$validated['email']}: {$otpCode}");

        return response()->json([
            'message' => 'If this email is registered, an OTP has been sent.',
            // 'otp' => $otpCode, // Uncomment for dev only
        ]);
    }

    /**
     * Verify the password reset OTP and return a reset token.
     */
    public function verifyPasswordResetOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
        ]);

        $otpRecord = Otp::where('phone', $validated['email'])
            ->where('code', $validated['otp'])
            ->where('expires_at', '>', Carbon::now())
            ->where('verified', false)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $otpRecord) {
            return response()->json(['error' => 'Invalid or expired OTP.'], 422);
        }

        $otpRecord->update(['verified' => true]);

        // Generate a temporary reset token (store hashed, return raw)
        $resetToken = Str::random(64);
        Cache::put(
            'password_reset_'.$validated['email'],
            Hash::make($resetToken),
            Carbon::now()->addMinutes(15)
        );

        return response()->json([
            'message' => 'OTP verified. You can now reset your password.',
            'reset_token' => $resetToken,
        ]);
    }

    /**
     * Reset password using the verified reset token.
     */
    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
            'reset_token' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $cachedHash = Cache::get('password_reset_'.$validated['email']);

        if (! $cachedHash || ! Hash::check($validated['reset_token'], $cachedHash)) {
            return response()->json(['error' => 'Invalid or expired reset token.'], 422);
        }

        $user = User::where('email', $validated['email'])->first();
        $user->update(['password' => Hash::make($validated['password'])]);

        // Clear the reset token
        Cache::forget('password_reset_'.$validated['email']);

        // Revoke all existing tokens to force re-login
        $user->tokens()->delete();

        return response()->json(['message' => 'Password reset successfully. Please login with your new password.']);
    }
}
