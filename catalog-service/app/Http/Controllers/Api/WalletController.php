<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    /**
     * Get wallet balance and transactions
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $transactions = WalletTransaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'balance' => (float) $user->wallet_balance,
            'transactions' => $transactions,
        ]);
    }

    /**
     * Create Razorpay order for wallet deposit
     */
    public function createDepositOrder(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1|max:50000',
        ]);

        $user = $request->user();
        $amount = (float) $validated['amount'];
        $receipt = 'wallet_'.$user->id.'_'.time();

        $razorpayOrder = PaymentService::createRazorpayOrder($receipt, $amount);

        return response()->json([
            'razorpay_order' => $razorpayOrder,
            'key_id' => config('services.razorpay.key_id'),
            'amount' => $amount,
            'currency' => 'INR',
        ]);
    }

    /**
     * Verify wallet deposit payment and credit balance
     */
    public function verifyDeposit(Request $request)
    {
        $validated = $request->validate([
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
            'amount' => 'required|numeric|min:1',
        ]);

        $result = PaymentService::verifyPayment(
            $validated['razorpay_order_id'],
            $validated['razorpay_payment_id'],
            $validated['razorpay_signature']
        );

        if (! $result['verified']) {
            return response()->json(['error' => 'Payment verification failed.'], 422);
        }

        $user = $request->user();
        $amount = (float) $validated['amount'];

        try {
            DB::beginTransaction();

            $user->increment('wallet_balance', $amount);

            WalletTransaction::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'type' => 'credit',
                'description' => 'Added money to wallet via Online Payment (ID: '.$validated['razorpay_payment_id'].')',
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Successfully deposited ₹'.number_format($amount, 2).' to your wallet.',
                'balance' => (float) $user->fresh()->wallet_balance,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['error' => 'Deposit failed. Please try again.'], 500);
        }
    }

    /**
     * Transfer money to another user's wallet
     */
    public function transfer(Request $request)
    {
        $validated = $request->validate([
            'recipient' => 'required|string', // Email or Phone
            'amount' => 'required|numeric|min:1',
        ]);

        $sender = $request->user();
        $amount = (float) $validated['amount'];

        if ($sender->wallet_balance < $amount) {
            return response()->json(['error' => 'Insufficient wallet balance.'], 422);
        }

        // Find recipient by email or phone
        $recipient = User::where('email', $validated['recipient'])
            ->orWhere('phone', $validated['recipient'])
            ->first();

        if (! $recipient) {
            return response()->json(['error' => 'Recipient user not found.'], 404);
        }

        if ($recipient->id === $sender->id) {
            return response()->json(['error' => 'You cannot transfer money to yourself.'], 422);
        }

        try {
            DB::beginTransaction();

            $recipientContact = $recipient->email ?? $recipient->phone;
            $senderContact = $sender->email ?? $sender->phone;

            // Deduct sender
            $sender->decrement('wallet_balance', $amount);
            WalletTransaction::create([
                'user_id' => $sender->id,
                'amount' => $amount,
                'type' => 'debit',
                'description' => "Transferred to {$recipient->name} ({$recipientContact})",
            ]);

            // Credit recipient
            $recipient->increment('wallet_balance', $amount);
            WalletTransaction::create([
                'user_id' => $recipient->id,
                'amount' => $amount,
                'type' => 'credit',
                'description' => "Received from {$sender->name} ({$senderContact})",
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Successfully transferred ₹'.number_format($amount, 2)." to {$recipient->name}.",
                'balance' => (float) $sender->fresh()->wallet_balance,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['error' => 'Transfer failed. Please try again.'], 500);
        }
    }
}
