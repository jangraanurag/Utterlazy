<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\WalletTransaction;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComplaintController extends Controller
{
    /**
     * Customer creates a complaint
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'reason' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
        ]);

        $user = $request->user();
        $order = Order::findOrFail($validated['order_id']);

        // Verify order belongs to user
        if ($order->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Only allow complaints for delivered orders
        if ($order->status !== 'delivered') {
            return response()->json(['error' => 'Complaints can only be filed for delivered orders'], 422);
        }

        $complaint = Complaint::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'restaurant_id' => $order->restaurant_id,
            'reason' => $validated['reason'],
            'description' => $validated['description'],
            'status' => 'pending',
        ]);

        // Notify vendor via FCM
        $fcm = app(FcmService::class);
        $restaurant = $order->restaurant;
        if ($restaurant) {
            $fcm->sendToRestaurant(
                $restaurant,
                [
                    'type' => 'new_complaint',
                    'complaint_id' => (string) $complaint->id,
                    'order_id' => (string) $order->id,
                    'screen' => 'complaint_detail',
                ],
                'New Complaint Received',
                "Order #{$order->id} — {$validated['reason']}"
            );
        }

        return response()->json([
            'message' => 'Complaint submitted successfully',
            'complaint' => $complaint,
        ], 201);
    }

    /**
     * Customer lists their complaints
     */
    public function myComplaints(Request $request)
    {
        $complaints = Complaint::where('user_id', $request->user()->id)
            ->with(['order:id,restaurant_id,grand_total,restaurant_name', 'restaurant:id,name'])
            ->latest()
            ->paginate(15);

        return response()->json($complaints);
    }

    /**
     * Vendor lists complaints for their restaurant
     */
    public function index(Request $request)
    {
        $restaurant = $request->user();
        $restaurantId = $restaurant->id;

        $query = Complaint::where('restaurant_id', $restaurantId)
            ->with(['user:id,name,email', 'order:id,grand_total,restaurant_name,created_at']);

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $complaints = $query->latest()->paginate(20);

        return response()->json($complaints);
    }

    /**
     * Vendor responds to a complaint
     */
    public function respond(Request $request, Complaint $complaint)
    {
        $restaurant = $request->user();

        if ($complaint->restaurant_id !== $restaurant->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($complaint->status === 'closed') {
            return response()->json(['error' => 'This complaint is already closed'], 422);
        }

        $validated = $request->validate([
            'vendor_response' => 'required|string|max:1000',
            'refund_amount' => 'nullable|numeric|min:0',
        ]);

        $refundAmount = $validated['refund_amount'] ?? 0;
        $order = $complaint->order;

        // Validate refund doesn't exceed order total
        if ($refundAmount > 0 && $refundAmount > $order->grand_total) {
            return response()->json(['error' => 'Refund amount cannot exceed order total of ₹'.$order->grand_total], 422);
        }

        DB::beginTransaction();

        try {
            // Update complaint
            $complaint->update([
                'vendor_response' => $validated['vendor_response'],
                'status' => 'responded',
                'refund_amount' => $refundAmount > 0 ? $refundAmount : null,
            ]);

            // Process refund if amount specified
            if ($refundAmount > 0) {
                $user = $complaint->user;

                // Credit customer wallet
                $user->increment('wallet_balance', $refundAmount);
                WalletTransaction::create([
                    'user_id' => $user->id,
                    'amount' => $refundAmount,
                    'type' => 'credit',
                    'description' => "Refund for complaint on Order #{$order->id}",
                ]);

                // Deduct from vendor payout (reduce delivery_fee_restaurant, not admin commission)
                Order::where('id', $order->id)->update([
                    'delivery_fee_restaurant' => DB::raw("GREATEST(0, delivery_fee_restaurant - {$refundAmount})"),
                ]);

                $complaint->update(['refund_processed' => true]);

                // Notify customer of refund
                $fcm = app(FcmService::class);
                $fcm->sendToUser(
                    $user,
                    'customer',
                    [
                        'type' => 'refund_processed',
                        'complaint_id' => (string) $complaint->id,
                        'order_id' => (string) $order->id,
                        'amount' => (string) $refundAmount,
                        'screen' => 'complaint_detail',
                    ],
                    'Refund Processed',
                    "₹{$refundAmount} has been added to your wallet"
                );

                // Notify vendor of refund deduction
                $restaurant = $complaint->restaurant;
                if ($restaurant) {
                    $fcm->sendToRestaurant(
                        $restaurant,
                        [
                            'type' => 'refund_processed',
                            'complaint_id' => (string) $complaint->id,
                            'order_id' => (string) $order->id,
                            'amount' => (string) $refundAmount,
                            'screen' => 'complaint_detail',
                        ],
                        'Refund Deducted',
                        "₹{$refundAmount} refunded to customer for Order #{$order->id} — will reflect in next payout"
                    );
                }
            } else {
                // Notify customer of response without refund
                $user = $complaint->user;
                $fcm = app(FcmService::class);
                $fcm->sendToUser(
                    $user,
                    'customer',
                    [
                        'type' => 'complaint_response',
                        'complaint_id' => (string) $complaint->id,
                        'order_id' => (string) $order->id,
                        'screen' => 'complaint_detail',
                    ],
                    'Complaint Response Received',
                    'Vendor has responded to your complaint'
                );
            }

            DB::commit();

            return response()->json([
                'message' => 'Response submitted'.($refundAmount > 0 ? ' with refund' : ''),
                'complaint' => $complaint->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['error' => 'Failed to process response'], 500);
        }
    }

    /**
     * Vendor closes a complaint
     */
    public function close(Request $request, Complaint $complaint)
    {
        $restaurant = $request->user();

        if ($complaint->restaurant_id !== $restaurant->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $complaint->update(['status' => 'closed']);

        return response()->json(['message' => 'Complaint closed', 'complaint' => $complaint->fresh()]);
    }
}
