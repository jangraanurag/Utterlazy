<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\SystemConfig;
use Illuminate\Http\Request;

class MembershipController extends Controller
{
    protected function isEnabled(): bool
    {
        return SystemConfig::getValue('membership_enabled', 'false') === 'true';
    }

    public function status(Request $request)
    {
        return response()->json([
            'enabled' => $this->isEnabled(),
            'membership' => $this->isEnabled() ? Membership::where('user_id', $request->user()->id)->first() : null,
        ]);
    }

    public function subscribe(Request $request)
    {
        if (! $this->isEnabled()) {
            return response()->json(['error' => 'Membership is currently disabled. Check back later!'], 503);
        }
        $validated = $request->validate(['plan' => 'required|in:plus,pro']);
        $prices = ['plus' => 299, 'pro' => 499];
        $benefits = [
            'plus' => ['free_delivery' => true, 'priority_support' => true, 'extra_coins' => 50],
            'pro' => ['free_delivery' => true, 'priority_support' => true, 'extra_coins' => 100, 'exclusive_offers' => true, 'early_access' => true],
        ];
        $membership = Membership::updateOrCreate(
            ['user_id' => $request->user()->id],
            ['plan' => $validated['plan'], 'starts_at' => now(), 'expires_at' => now()->addMonth(), 'is_active' => true, 'benefits' => $benefits[$validated['plan']]]
        );

        return response()->json(['message' => 'Subscribed!', 'membership' => $membership, 'price' => $prices[$validated['plan']]]);
    }

    public function cancel(Request $request)
    {
        Membership::where('user_id', $request->user()->id)->update(['is_active' => false]);

        return response()->json(['message' => 'Membership cancelled']);
    }
}
