<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function myCode(Request $request)
    {
        $referral = Referral::firstOrCreate(
            ['referrer_id' => $request->user()->id, 'referred_id' => null],
            ['code' => 'UTTERLAZY'.strtoupper(substr(md5($request->user()->id), 0, 6)), 'referrer_reward' => 100, 'referred_reward' => 50]
        );

        $totalReferrals = Referral::where('referrer_id', $request->user()->id)
            ->where('status', 'completed')
            ->count();

        $totalEarned = Referral::where('referrer_id', $request->user()->id)
            ->where('status', 'completed')
            ->sum('referrer_reward');

        return response()->json([
            'code' => $referral->code,
            'total_referrals' => $totalReferrals,
            'total_earned' => $totalEarned,
            'referrer_reward' => (float) $referral->referrer_reward,
            'referred_reward' => (float) $referral->referred_reward,
        ]);
    }

    public function apply(Request $request)
    {
        $validated = $request->validate(['code' => 'required|string']);

        // Find the template referral code (where referred_id is null)
        $referral = Referral::where('code', strtoupper($validated['code']))
            ->where('referred_id', null)
            ->where('referrer_id', '!=', $request->user()->id)
            ->first();

        if (! $referral) {
            return response()->json(['error' => 'Invalid referral code'], 422);
        }

        $existing = Referral::where('referred_id', $request->user()->id)->first();
        if ($existing) {
            return response()->json(['error' => 'You have already used a referral code'], 422);
        }

        // Create a pending referral instance
        Referral::create([
            'referrer_id' => $referral->referrer_id,
            'referred_id' => $request->user()->id,
            'code' => $referral->code.'_'.$request->user()->id,
            'status' => 'pending',
            'referrer_reward' => 0.00,
            'referred_reward' => 0.00,
        ]);

        return response()->json([
            'message' => 'Referral code applied! Reward will be credited after your first order is delivered.',
        ]);
    }
}
