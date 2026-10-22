<?php

namespace App\Services;

use App\Models\ApprovalWorkflowInstance;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Support reference implementation (§6).
 *
 * Complaint refunds (restaurant-acknowledged, above DOA limits) travel
 * through the generic engine before the wallet credit is processed.
 * Final approval executes the refund: wallet credit + ledger entry +
 * vendor payout deduction.
 */
class SupportApprovalBridge
{
    /**
     * Originate a complaint refund for approval.
     * Route amount = refund amount agreed by the restaurant.
     */
    public static function originateComplaintRefund(Complaint $complaint, User $actor, bool $isException = false): ApprovalWorkflowInstance
    {
        $restaurant = $complaint->restaurant;
        $amount = (float) $complaint->refund_amount;

        return ApprovalWorkflowEngine::originate(
            department: 'support',
            entityType: 'complaint_refund',
            entityId: (string) $complaint->id,
            amount: $amount,
            geography: [
                'state' => $restaurant?->state,
                'district' => $restaurant?->district,
            ],
            isException: $isException || $restaurant?->state === null,
            originOrgUnitId: null,
            actor: $actor
        );
    }

    /**
     * Downstream action on final approval: process the refund.
     */
    public static function onComplaintRefundApproved(ApprovalWorkflowInstance $instance): void
    {
        $complaint = Complaint::find($instance->entity_id);
        if (! $complaint || $complaint->refund_processed || $complaint->status === 'closed') {
            return;
        }

        $amount = (float) $complaint->refund_amount;
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($complaint, $amount) {
            $order = $complaint->order;
            $user = $complaint->user;

            if ($user) {
                $user->increment('wallet_balance', $amount);
                WalletTransaction::create([
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'type' => 'credit',
                    'description' => "Refund for complaint on Order #{$complaint->order_id}",
                ]);
            }

            if ($order) {
                $fee = max(0, (float) $order->delivery_fee_restaurant - $amount);
                Order::where('id', $order->id)->update(['delivery_fee_restaurant' => $fee]);
            }

            $complaint->update([
                'status' => 'resolved',
                'refund_processed' => true,
            ]);
        });
    }

    public static function summary(string $entityType, string $entityId): array
    {
        $complaint = Complaint::with(['restaurant', 'order', 'user'])->find($entityId);
        if (! $complaint) {
            return ['type' => 'complaint_refund', 'id' => $entityId, 'label' => 'Refund #' . substr($entityId, 0, 8)];
        }

        return [
            'type' => 'complaint_refund',
            'id' => (string) $complaint->id,
            'label' => 'Refund ₹' . (float) $complaint->refund_amount . ' — Order #' . $complaint->order_id,
            'order_id' => $complaint->order_id,
            'customer' => $complaint->user?->name,
            'restaurant' => $complaint->restaurant?->name,
            'restaurant_state' => $complaint->restaurant?->state,
            'restaurant_district' => $complaint->restaurant?->district,
            'reason' => $complaint->reason,
            'refund_amount' => (float) $complaint->refund_amount,
            'route_amount' => (float) $complaint->refund_amount,
            'status' => $complaint->status,
        ];
    }
}
