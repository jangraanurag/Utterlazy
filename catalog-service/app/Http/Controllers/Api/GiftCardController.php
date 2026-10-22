<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use Illuminate\Http\Request;

class GiftCardController extends Controller
{
    public function purchase(Request $request)
    {
        $validated = $request->validate(['amount' => 'required|numeric|min:100|max:5000', 'recipient_email' => 'required|email', 'message' => 'sometimes|string|max:200']);
        $code = strtoupper(bin2hex(random_bytes(6)));
        $card = GiftCard::create(['code' => $code, 'initial_amount' => $validated['amount'], 'remaining_amount' => $validated['amount'], 'buyer_id' => $request->user()->id, 'recipient_email' => $validated['recipient_email'], 'message' => $validated['message'] ?? null, 'expires_at' => now()->addYear()]);

        return response()->json(['message' => 'Gift card purchased!', 'gift_card' => $card], 201);
    }

    public function redeem(Request $request)
    {
        $validated = $request->validate(['code' => 'required|string']);
        $card = GiftCard::where('code', $validated['code'])->where('is_redeemed', false)->where('expires_at', '>', now())->first();
        if (! $card) {
            return response()->json(['error' => 'Invalid or expired gift card'], 422);
        }
        $card->update(['recipient_id' => $request->user()->id]);

        return response()->json(['message' => 'Gift card added! ₹'.$card->remaining_amount.' balance', 'gift_card' => $card]);
    }

    public function balance(Request $request)
    {
        $total = GiftCard::where('recipient_id', $request->user()->id)->where('is_redeemed', false)->where('expires_at', '>', now())->sum('remaining_amount');

        return response()->json(['balance' => $total]);
    }
}
