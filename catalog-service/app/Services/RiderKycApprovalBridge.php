<?php

namespace App\Services;

use App\Models\ApprovalWorkflowInstance;
use App\Models\Rider;
use App\Models\User;
use Carbon\Carbon;

/**
 * Rider Ops reference implementation (§6).
 *
 * Rider KYC verification queues travel through the generic engine at the
 * appropriate geographic level (district fleet coordinator → state →
 * national). Final approval marks the rider verified and approves their
 * pending documents.
 */
class RiderKycApprovalBridge
{
    public static function originateKycVerification(Rider $rider, User $actor, bool $isException = false): ApprovalWorkflowInstance
    {
        return ApprovalWorkflowEngine::originate(
            department: 'rider_ops',
            entityType: 'kyc_verification',
            entityId: $rider->id,
            amount: 0,
            geography: [
                'state' => $rider->state,
                'district' => $rider->district,
            ],
            isException: $isException || $rider->state === null,
            originOrgUnitId: null,
            actor: $actor
        );
    }

    /**
     * Downstream action on final approval: verify the rider + approve docs.
     */
    public static function onKycVerificationApproved(ApprovalWorkflowInstance $instance): void
    {
        $rider = Rider::find($instance->entity_id);
        if (! $rider) {
            return;
        }

        $rider->update([
            'is_verified' => true,
            'verification_status' => 'verified',
            'verification_notes' => null,
        ]);

        $rider->documents()->update([
            'status' => 'approved',
            'rejection_reason' => null,
            'verified_at' => Carbon::now(),
            'verified_by' => $instance->steps()
                ->where('action', ApprovalWorkflowEngine::ACTION_APPROVED)
                ->orderByDesc('created_at')->first()?->actor_user_id,
        ]);
    }

    public static function summary(string $entityType, string $entityId): array
    {
        $rider = Rider::with('documents')->find($entityId);
        if (! $rider) {
            return ['type' => 'kyc_verification', 'id' => $entityId, 'label' => 'Rider KYC #' . substr($entityId, 0, 8)];
        }

        $docs = $rider->documents;
        $required = ['aadhaar_front', 'aadhaar_back', 'driving_license_front', 'pan_card', 'selfie'];
        $statuses = $docs->groupBy('document_type')->map(fn ($g) => $g->first()->status)->toArray();

        return [
            'type' => 'kyc_verification',
            'id' => $rider->id,
            'label' => 'KYC — ' . $rider->name,
            'rider' => $rider->name,
            'phone' => $rider->phone,
            'state' => $rider->state,
            'district' => $rider->district,
            'vehicle' => ($rider->vehicle_type ?? '') . ' ' . ($rider->vehicle_number ?? ''),
            'documents_uploaded' => $docs->count(),
            'documents_required' => count($required),
            'document_statuses' => $statuses,
            'route_amount' => 0,
        ];
    }
}
