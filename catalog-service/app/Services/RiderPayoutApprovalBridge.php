<?php

namespace App\Services;

use App\Models\ApprovalWorkflowInstance;
use App\Models\RiderPayoutCycle;
use App\Models\User;
use Carbon\Carbon;

/**
 * Rider Ops reference implementation (§6).
 *
 * Weekly rider payout cycles travel through the generic engine. Final
 * approval marks the cycle approved for payment (UTR entry stays with
 * AdminController::payRiderPayoutCycle).
 */
class RiderPayoutApprovalBridge
{
    /**
     * Originate a rider payout cycle for approval at the unit covering the
     * rider's geography (§5.2 step 1).
     */
    public static function originateRiderPayout(RiderPayoutCycle $cycle, User $actor, bool $isException = false): ApprovalWorkflowInstance
    {
        $rider = $cycle->rider;

        return ApprovalWorkflowEngine::originate(
            department: 'rider_ops',
            entityType: 'rider_payout',
            entityId: $cycle->id,
            amount: (float) $cycle->net_payable,
            geography: [
                'state' => $rider?->state,
                'district' => $rider?->district,
            ],
            isException: $isException || $rider?->state === null,
            originOrgUnitId: null,
            actor: $actor
        );
    }

    /**
     * Downstream action on final approval: mark the cycle approved for payment.
     */
    public static function onRiderPayoutApproved(ApprovalWorkflowInstance $instance): void
    {
        $cycle = RiderPayoutCycle::find($instance->entity_id);
        if (! $cycle) {
            return;
        }

        $cycle->status = 'pending_payment';
        $cycle->approved_by = $instance->steps()
            ->where('action', ApprovalWorkflowEngine::ACTION_APPROVED)
            ->orderByDesc('created_at')->first()?->actor_user_id;
        $cycle->approved_at = Carbon::now();
        $cycle->save();
    }

    public static function summary(string $entityId): array
    {
        $cycle = RiderPayoutCycle::with('rider')->find($entityId);
        if (! $cycle) {
            return ['type' => 'rider_payout', 'id' => $entityId, 'label' => 'Rider payout cycle #' . substr($entityId, 0, 8)];
        }

        return [
            'type' => 'rider_payout',
            'id' => $cycle->id,
            'label' => 'Weekly rider payout — ' . ($cycle->rider?->name ?? 'Rider'),
            'rider' => $cycle->rider?->name,
            'rider_phone' => $cycle->rider?->phone,
            'rider_state' => $cycle->rider?->state,
            'rider_district' => $cycle->rider?->district,
            'cycle_date' => $cycle->cycle_date?->toDateString(),
            'net_payable' => (float) $cycle->net_payable,
            'orders' => (int) $cycle->total_orders,
            'distance_km' => (float) $cycle->total_distance_km,
            'utr' => $cycle->utr_number,
        ];
    }
}
