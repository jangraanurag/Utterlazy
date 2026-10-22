<?php

namespace App\Services;

use App\Models\ApprovalThresholdConfig;
use App\Models\ApprovalWorkflowInstance;
use App\Models\ApprovalWorkflowStep;
use App\Models\OrgRole;
use App\Models\OrgUnit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Generic Approval Workflow Engine (§5).
 *
 * Operates on any Approvable Entity (Payment, Payout exception, Invoice
 * correction, KYC verification, ...) routed through a department's org tree.
 * Threshold-based: each level approves up to its own ₹/risk threshold; only
 * over-threshold or exception items escalate upward.
 */
class ApprovalWorkflowEngine
{
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_SENT_BACK = 'sent_back';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const ACTION_ORIGINATED = 'originated';
    public const ACTION_APPROVED = 'approved';
    public const ACTION_ESCALATED = 'escalated';
    public const ACTION_SENT_BACK = 'sent_back';
    public const ACTION_RESUBMITTED = 'resubmitted';
    public const ACTION_REJECTED = 'rejected';
    public const ACTION_AUTO_ESCALATED = 'auto_escalated';
    public const ACTION_SENT_BACK_TIMEOUT = 'sent_back_timeout';

    /**
     * Map entity_type => downstream action invoked on final approval.
     * Modules plug their Approvable Entity in here.
     */
    protected static array $finalizers = [
        'vendor_payout' => [FinanceApprovalBridge::class, 'onVendorPayoutApproved'],
        'invoice' => [FinanceApprovalBridge::class, 'onInvoiceApproved'],
        'recovery' => [FinanceApprovalBridge::class, 'onRecoveryApproved'],
        'gst_filing' => [FinanceApprovalBridge::class, 'onGstFilingApproved'],
        'rider_payout' => [RiderPayoutApprovalBridge::class, 'onRiderPayoutApproved'],
        'kyc_verification' => [RiderKycApprovalBridge::class, 'onKycVerificationApproved'],
        'sponsored_listing' => [AccountManagementApprovalBridge::class, 'onSponsoredListingApproved'],
        'offer' => [AccountManagementApprovalBridge::class, 'onOfferApproved'],
        'restaurant_assignment' => [AccountManagementApprovalBridge::class, 'onRestaurantAssignmentApproved'],
        'wa_placement' => [AccountManagementApprovalBridge::class, 'onWaPlacementApproved'],
        'complaint_refund' => [SupportApprovalBridge::class, 'onComplaintRefundApproved'],
        'food_waste_claim' => [FinanceApprovalBridge::class, 'onFoodWasteClaimApproved'],
    ];

    /**
     * Originate a new workflow at the lowest relevant org unit (§5.2 step 1).
     */
    public static function originate(
        string $department,
        string $entityType,
        string $entityId,
        ?float $amount,
        array $geography = [],
        bool $isException = false,
        ?string $originOrgUnitId = null,
        ?User $actor = null
    ): ApprovalWorkflowInstance {
        $origin = self::resolveOriginOrgUnit($department, $geography, $originOrgUnitId);
        if (! $origin) {
            throw ValidationException::withMessages([
                'org_unit' => 'No org unit covers this geography for department: ' . $department,
            ]);
        }

        $instance = ApprovalWorkflowInstance::create([
            'department' => $department,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'origin_org_unit_id' => $origin->id,
            'current_org_unit_id' => $origin->id,
            'current_org_role_id' => self::resolveRoleForUnit($origin)?->id,
            'status' => self::STATUS_UNDER_REVIEW,
            'amount' => $amount,
            'is_exception' => $isException,
            'threshold_snapshot' => self::thresholdSnapshotFor($origin, $entityType, $department, $geography),
        ]);

        $landing = self::landInstance($instance, pin: true);
        $instance->steps()->create([
            'org_unit_id' => $origin->id,
            'actor_user_id' => $actor?->id,
            'action' => self::ACTION_ORIGINATED,
            'metadata' => ['amount' => $amount, 'is_exception' => $isException],
        ]);

        // Auto-escalate if the origin role is vacant (§5.4)
        return $landing['vacancy'] ? self::handleVacancy($instance, $origin) : $instance;
    }

    /**
     * Approve the item at the current unit — the fast path within threshold (§5.2 step 3).
     * If above the unit's threshold the engine refuses and expects escalation.
     */
    public static function approve(ApprovalWorkflowInstance $instance, User $user, string $comment = ''): ApprovalWorkflowInstance
    {
        self::assertReviewer($instance, $user);

        if ($instance->status === self::STATUS_SENT_BACK) {
            return self::resubmit($instance, $user, $comment);
        }

        $landing = self::landInstance($instance);
        $maxAmount = $landing['max_amount'];

        if (! $instance->is_exception && $maxAmount !== null && ($instance->amount ?? 0) > $maxAmount) {
            throw ValidationException::withMessages([
                'action' => "Amount ₹{$instance->amount} exceeds this unit's approval threshold of ₹{$maxAmount}. Escalate instead.",
            ]);
        }

        $instance->steps()->create([
            'org_unit_id' => $instance->current_org_unit_id,
            'org_role_id' => $instance->current_org_role_id,
            'actor_user_id' => $user->id,
            'action' => self::ACTION_APPROVED,
            'comment' => $comment,
            'metadata' => ['amount' => $instance->amount],
        ]);

        $instance->status = self::STATUS_APPROVED;
        $instance->completed_at = Carbon::now();
        $instance->sla_due_at = null;
        $instance->save();

        self::runFinalizer($instance);

        return $instance;
    }

    /**
     * Escalate to the parent org unit — auto-resolved from the tree (§5.2 step 4).
     */
    public static function escalate(ApprovalWorkflowInstance $instance, User $user, string $comment = ''): ApprovalWorkflowInstance
    {
        self::assertReviewer($instance, $user);

        if ($instance->status === self::STATUS_SENT_BACK) {
            throw ValidationException::withMessages(['action' => 'This item was sent back. Resubmit it from the target unit first.']);
        }

        $parent = $instance->currentOrgUnit?->parent;
        if (! $parent) {
            throw ValidationException::withMessages(['action' => 'This is the top of the branch — approve or send back for clarification instead.']);
        }

        $instance->steps()->create([
            'org_unit_id' => $instance->current_org_unit_id,
            'org_role_id' => $instance->current_org_role_id,
            'actor_user_id' => $user->id,
            'action' => self::ACTION_ESCALATED,
            'comment' => $comment,
            'to_org_unit_id' => $parent->id,
            'metadata' => ['amount' => $instance->amount],
        ]);

        $instance->current_org_unit_id = $parent->id;
        $instance->current_org_role_id = self::resolveRoleForUnit($parent)?->id;
        $instance->save();

        $landing = self::landInstance($instance, pin: true);

        return $landing['vacancy'] ? self::handleVacancy($instance, $parent) : $instance;
    }

    /**
     * Send back for clarification to ANY lower unit in the chain (§5.2 step 5).
     * Required comment; upward progress pauses until the target resubmits.
     */
    public static function sendBack(ApprovalWorkflowInstance $instance, User $user, string $targetOrgUnitId, string $comment): ApprovalWorkflowInstance
    {
        self::assertReviewer($instance, $user);

        if ($instance->status === self::STATUS_SENT_BACK) {
            throw ValidationException::withMessages(['action' => 'This item is already sent back.']);
        }

        $target = OrgUnit::findOrFail($targetOrgUnitId);
        self::assertTargetInChain($instance, $target);

        $instance->steps()->create([
            'org_unit_id' => $instance->current_org_unit_id,
            'org_role_id' => $instance->current_org_role_id,
            'actor_user_id' => $user->id,
            'action' => self::ACTION_SENT_BACK,
            'comment' => $comment,
            'to_org_unit_id' => $target->id,
            'metadata' => ['sent_back_from' => $instance->current_org_unit_id],
        ]);

        $instance->current_org_unit_id = $target->id;
        $instance->current_org_role_id = self::resolveRoleForUnit($target)?->id;
        $instance->status = self::STATUS_SENT_BACK;
        $instance->save();

        $landing = self::landInstance($instance, pin: true);

        return $landing['vacancy'] ? self::handleVacancy($instance, $target) : $instance;
    }

    /**
     * The flagged unit responds and resubmits — returns to the unit that sent it back (§5.2 step 5).
     */
    public static function resubmit(ApprovalWorkflowInstance $instance, User $user, string $comment = ''): ApprovalWorkflowInstance
    {
        self::assertReviewer($instance, $user);

        if ($instance->status !== self::STATUS_SENT_BACK) {
            throw ValidationException::withMessages(['action' => 'Only sent-back items can be resubmitted.']);
        }

        $lastSendBack = $instance->steps()
            ->where('action', self::ACTION_SENT_BACK)
            ->orderByDesc('created_at')->first();
        $backTo = $lastSendBack?->org_unit_id ??
            ($lastSendBack ? OrgUnit::find($lastSendBack->org_unit_id)?->parent?->id : null);

        if (! $backTo) {
            throw ValidationException::withMessages(['action' => 'Cannot determine the unit that sent this back.']);
        }

        $target = OrgUnit::findOrFail($backTo);

        $instance->steps()->create([
            'org_unit_id' => $instance->current_org_unit_id,
            'org_role_id' => $instance->current_org_role_id,
            'actor_user_id' => $user->id,
            'action' => self::ACTION_RESUBMITTED,
            'comment' => $comment,
            'to_org_unit_id' => $target->id,
        ]);

        $instance->current_org_unit_id = $target->id;
        $instance->current_org_role_id = self::resolveRoleForUnit($target)?->id;
        $instance->status = self::STATUS_UNDER_REVIEW;
        $instance->save();

        $landing = self::landInstance($instance, pin: true);

        return $landing['vacancy'] ? self::handleVacancy($instance, $target) : $instance;
    }

    /**
     * Reject — terminal state.
     */
    public static function reject(ApprovalWorkflowInstance $instance, User $user, string $comment): ApprovalWorkflowInstance
    {
        self::assertReviewer($instance, $user);

        if ($instance->status === self::STATUS_SENT_BACK) {
            throw ValidationException::withMessages(['action' => 'Sent-back items must be resubmitted or rejected from the target unit.']);
        }

        $instance->steps()->create([
            'org_unit_id' => $instance->current_org_unit_id,
            'org_role_id' => $instance->current_org_role_id,
            'actor_user_id' => $user->id,
            'action' => self::ACTION_REJECTED,
            'comment' => $comment,
        ]);

        $instance->status = self::STATUS_REJECTED;
        $instance->completed_at = Carbon::now();
        $instance->sla_due_at = null;
        $instance->save();

        return $instance;
    }

    /**
     * SLA auto-escalation (§5.3). Run from scheduler.
     */
    public static function checkSlaTimeouts(): array
    {
        $handled = ['escalated' => 0, 'sent_back' => 0];

        $overdue = ApprovalWorkflowInstance::where('status', self::STATUS_UNDER_REVIEW)
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', Carbon::now())
            ->with('currentOrgUnit')
            ->get();

        foreach ($overdue as $instance) {
            $parent = $instance->currentOrgUnit?->parent;
            if (! $parent) {
                continue; // top of branch: flagged, stays until final authority acts
            }

            $instance->steps()->create([
                'org_unit_id' => $instance->current_org_unit_id,
                'org_role_id' => $instance->current_org_role_id,
                'action' => self::ACTION_AUTO_ESCALATED,
                'comment' => 'SLA exceeded — auto-escalated',
                'to_org_unit_id' => $parent->id,
                'metadata' => ['reason' => 'sla_timeout'],
            ]);

            $instance->current_org_unit_id = $parent->id;
            $instance->current_org_role_id = self::resolveRoleForUnit($parent)?->id;
            $instance->save();
            self::landInstance($instance, pin: true);
            $handled['escalated']++;
        }

        $sentBackOverdue = ApprovalWorkflowInstance::where('status', self::STATUS_SENT_BACK)
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', Carbon::now())
            ->with('currentOrgUnit')
            ->get();

        foreach ($sentBackOverdue as $instance) {
            $lastSendBack = $instance->steps()
                ->where('action', self::ACTION_SENT_BACK)
                ->orderByDesc('created_at')->first();
            $backToId = $lastSendBack?->to_org_unit_id;
            $backTo = $backToId ? OrgUnit::find($backToId) : null;
            if (! $backTo) {
                continue;
            }

            $instance->steps()->create([
                'org_unit_id' => $instance->current_org_unit_id,
                'org_role_id' => $instance->current_org_role_id,
                'action' => self::ACTION_SENT_BACK_TIMEOUT,
                'comment' => 'Clarification response SLA exceeded — returned to reviewer',
                'to_org_unit_id' => $backTo->id,
            ]);

            $instance->current_org_unit_id = $backTo->id;
            $instance->current_org_role_id = self::resolveRoleForUnit($backTo)?->id;
            $instance->status = self::STATUS_UNDER_REVIEW;
            $instance->save();
            self::landInstance($instance, pin: true);
            $handled['sent_back']++;
        }

        return $handled;
    }

    /**
     * Items currently awaiting the given user's action (assigned or backup).
     */
    public static function queueForUser(User $user, array $filters = []): array
    {
        $roleIds = OrgRole::where(function ($q) use ($user) {
            $q->where('assigned_user_id', $user->id)
              ->orWhere('backup_user_id', $user->id);
        })->where('is_active', true)->pluck('id');

        $query = ApprovalWorkflowInstance::with(['currentOrgUnit', 'currentOrgRole.assignedUser', 'currentOrgRole.backupUser'])
            ->whereIn('current_org_role_id', $roleIds->all())
            ->whereIn('status', [self::STATUS_UNDER_REVIEW, self::STATUS_SENT_BACK]);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['department'])) {
            $query->where('department', $filters['department']);
        }
        if (! empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }

        $query->orderByRaw('sla_due_at is null')->orderBy('sla_due_at');

        return $query->get()->map(fn ($i) => self::decorate($i))->all();
    }

    /**
     * Decorate an instance for API output (queue card + detail).
     */
    public static function decorate(ApprovalWorkflowInstance $instance): array
    {
        $landing = self::landInstance($instance);

        return [
            'id' => $instance->id,
            'department' => $instance->department,
            'entity_type' => $instance->entity_type,
            'entity_id' => $instance->entity_id,
            'entity_summary' => self::entitySummary($instance->entity_type, $instance->entity_id),
            'amount' => $instance->amount,
            'is_exception' => $instance->is_exception,
            'status' => $instance->status,
            'status_label' => match ($instance->status) {
                self::STATUS_SENT_BACK => 'Sent back for clarification',
                self::STATUS_APPROVED => 'Approved',
                self::STATUS_REJECTED => 'Rejected',
                default => 'Under review',
            },
            'origin_org_unit' => $instance->originOrgUnit?->name,
            'current_org_unit' => $instance->currentOrgUnit()->first()?->name,
            'current_unit_type' => $instance->currentOrgUnit()->first()?->unit_type,
            'max_amount' => $landing['max_amount'],
            'pinned_max_amount' => $instance->pinned_max_amount,
            'approvable' => $landing['approvable'],
            'reviewer' => $landing['reviewer_name'],
            'is_vacant' => $landing['vacancy'],
            'sla_due_at' => $instance->sla_due_at?->setTimezone('Asia/Kolkata')->toIso8601String(),
            'sla_breached' => $instance->sla_due_at ? $instance->sla_due_at->isPast() : false,
            'created_at' => $instance->created_at->setTimezone('Asia/Kolkata')->toIso8601String(),
            'completed_at' => $instance->completed_at?->setTimezone('Asia/Kolkata')->toIso8601String(),
        ];
    }

    /**
     * Full journey for visualization + audit (§8 workflow visualization).
     */
    public static function timeline(ApprovalWorkflowInstance $instance): array
    {
        $steps = $instance->steps()->with(['orgUnit', 'toOrgUnit', 'actorUser'])->get()->map(function ($step) {
            return [
                'action' => $step->action,
                'action_label' => self::actionLabel($step->action),
                'org_unit' => $step->orgUnit?->name,
                'to_org_unit' => $step->toOrgUnit?->name,
                'actor' => $step->actorUser?->name ?? 'System',
                'comment' => $step->comment,
                'metadata' => $step->metadata,
                'at' => $step->created_at->setTimezone('Asia/Kolkata')->toIso8601String(),
            ];
        });

        $landing = self::landInstance($instance);

        return [
            'instance' => self::decorate($instance),
            'threshold_snapshot' => $instance->threshold_snapshot,
            'steps' => $steps,
            'waiting_seconds' => $instance->sla_due_at ? max(0, $instance->sla_due_at->diffInSeconds(Carbon::now())) : null,
            'max_amount' => $landing['max_amount'],
        ];
    }

    /**
     * Resolve the deepest org unit covering the given geography (§7 routing).
     */
    public static function resolveOriginOrgUnit(string $department, array $geography, ?string $fallback = null): ?OrgUnit
    {
        if ($fallback) {
            return OrgUnit::find($fallback);
        }

        $units = OrgUnit::where('department', $department)
            ->where('is_active', true)
            ->whereNotNull('geography_scope')
            ->get();

        $state = $geography['state'] ?? null;
        $district = $geography['district'] ?? null;
        $pincodes = $geography['pincodes'] ?? [];

        $best = null;
        $bestScore = -1;
        foreach ($units as $unit) {
            $scope = $unit->geography_scope ?? [];
            $matchesState = ! $state || collect($scope)->contains(fn ($s) => ($s['state'] ?? null) === $state);
            // A district/region unit only covers its own district — an item
            // without a district (e.g. state-level GST filing) must not land
            // on district-scoped units; it belongs to the state/national level.
            $matchesDistrict = $district
                ? collect($scope)->contains(fn ($s) => ($s['state'] ?? null) === $state && ($s['district'] ?? null) === $district)
                : collect($scope)->every(fn ($s) => ($s['district'] ?? null) === null);
            $matchesPincode = empty($pincodes) || collect($scope)->contains(fn ($s) => count(array_intersect($s['pincodes'] ?? [], $pincodes)) > 0);
            if (! $matchesState || ! $matchesDistrict || ! $matchesPincode) {
                continue;
            }

            $depth = self::treeDepth($unit);
            $score = $depth * 10
                + ($matchesPincode ? 5 : 0)
                + ($matchesDistrict ? 2 : 0)
                + ($matchesState ? 1 : 0);
            if ($score > $bestScore) {
                $best = $unit;
                $bestScore = $score;
            }
        }

        return $best;
    }

    public static function treeDepth(OrgUnit $unit): int
    {
        $depth = 0;
        $cursor = $unit;
        while ($cursor->parent_org_unit_id) {
            $depth++;
            $cursor = $cursor->parent;
            if (! $cursor) {
                break;
            }
        }
        return $depth;
    }

    /**
     * Active role for a unit — or null if vacant/unassigned.
     */
    public static function resolveRoleForUnit(OrgUnit $unit): ?OrgRole
    {
        return OrgRole::where('org_unit_id', $unit->id)->where('is_active', true)->first();
    }

    /**
     * Resolve who can act on the current unit: assigned user, else backup (§5.4).
     */
    public static function resolveReviewer(OrgUnit $unit): ?array
    {
        $role = self::resolveRoleForUnit($unit);
        if (! $role) {
            return null;
        }

        if ($role->assigned_user_id) {
            $user = User::find($role->assigned_user_id);
            if ($user && $user->is_active !== false) {
                return ['role' => $role, 'user' => $user, 'via_backup' => false];
            }
        }

        if ($role->backup_user_id) {
            $user = User::find($role->backup_user_id);
            if ($user && $user->is_active !== false) {
                return ['role' => $role, 'user' => $user, 'via_backup' => true];
            }
        }

        return null;
    }

    /**
     * Compute threshold + landing metadata for the instance's current unit.
     *
     * §10 immutability: the threshold in force when the item LANDED at this unit
     * is pinned onto the instance (pin=true at originate/escalate/send-back/
     * resubmit/SLA moves). Every later read (approve/decorate) uses the pinned
     * values, so admin config changes never retroactively alter in-flight items.
     */
    public static function landInstance(ApprovalWorkflowInstance $instance, bool $pin = false): array
    {
        $unit = $instance->currentOrgUnit()->first();
        if (! $unit) {
            return ['max_amount' => null, 'approvable' => false, 'reviewer_name' => null, 'vacancy' => false];
        }

        $config = self::thresholdFor($unit, $instance->entity_type, $instance->department);
        $liveMax = $config?->max_amount;
        $liveSla = $config?->sla_hours ?? 24;

        if ($pin) {
            $instance->pinned_max_amount = $liveMax;
            $instance->pinned_sla_hours = $liveSla;
            $instance->save();
        }

        $maxAmount = $instance->pinned_max_amount ?? $liveMax;
        $slaHours = $instance->pinned_sla_hours ?? $liveSla;

        $isTop = $unit->parent_org_unit_id === null;
        $approvable = ! $instance->is_exception && ($maxAmount === null || ($instance->amount ?? 0) <= $maxAmount);
        $reviewer = self::resolveReviewer($unit);

        if ($instance->sla_due_at === null && in_array($instance->status, [self::STATUS_UNDER_REVIEW, self::STATUS_SENT_BACK])) {
            $instance->sla_due_at = Carbon::now()->addHours($slaHours);
            $instance->save();
        }

        return [
            'max_amount' => $maxAmount,
            'sla_hours' => $slaHours,
            'approvable' => $approvable,
            'is_top' => $isTop,
            'reviewer' => $reviewer,
            'reviewer_name' => $reviewer ? $reviewer['user']->name . ($reviewer['via_backup'] ? ' (backup)' : '') : null,
            'vacancy' => $reviewer === null,
        ];
    }

    /**
     * Vacant role handling (§5.4): auto-escalate to parent; at top, flag and wait.
     */
    protected static function handleVacancy(ApprovalWorkflowInstance $instance, OrgUnit $unit): ApprovalWorkflowInstance
    {
        $parent = $unit->parent;
        if (! $parent) {
            $instance->steps()->create([
                'org_unit_id' => $unit->id,
                'action' => self::ACTION_AUTO_ESCALATED,
                'comment' => 'Role vacant with no backup — awaiting assignment at top of branch',
                'metadata' => ['reason' => 'vacancy', 'stuck' => true],
            ]);
            $instance->current_org_role_id = null;
            $instance->save();
            return $instance;
        }

        $instance->steps()->create([
            'org_unit_id' => $unit->id,
            'org_role_id' => $instance->current_org_role_id,
            'action' => self::ACTION_AUTO_ESCALATED,
            'comment' => 'Role vacant or assignee unavailable — auto-escalated to ' . $parent->name,
            'to_org_unit_id' => $parent->id,
            'metadata' => ['reason' => 'vacancy'],
        ]);

        $instance->current_org_unit_id = $parent->id;
        $instance->current_org_role_id = self::resolveRoleForUnit($parent)?->id;
        $instance->sla_due_at = null;
        $instance->save();

        return self::landInstance($instance, pin: true)['vacancy']
            ? self::handleVacancy($instance, $parent)
            : $instance;
    }

    protected static function thresholdFor(OrgUnit $unit, string $entityType, string $department): ?ApprovalThresholdConfig
    {
        return ApprovalThresholdConfig::where('department', $department)
            ->where('unit_type', $unit->unit_type)
            ->where('entity_type', $entityType)
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->first();
    }

    protected static function thresholdSnapshotFor(OrgUnit $unit, string $entityType, string $department, array $geography): array
    {
        $config = self::thresholdFor($unit, $entityType, $department);

        return [
            'department' => $department,
            'entity_type' => $entityType,
            'unit_type' => $unit->unit_type,
            'org_unit_id' => $unit->id,
            'max_amount' => $config?->max_amount,
            'sla_hours' => $config?->sla_hours,
            'geography' => $geography,
        ];
    }

    protected static function assertReviewer(ApprovalWorkflowInstance $instance, User $user): void
    {
        $reviewer = self::resolveReviewer($instance->currentOrgUnit);
        if (! $reviewer || $reviewer['user']->id !== $user->id) {
            throw ValidationException::withMessages([
                'action' => 'You are not the assigned (or backup) reviewer for ' . $instance->currentOrgUnit?->name,
            ]);
        }
    }

    protected static function assertTargetInChain(ApprovalWorkflowInstance $instance, OrgUnit $target): void
    {
        $chain = self::chainFrom($instance->originOrgUnit, $instance->currentOrgUnit);
        $chainIds = collect($chain)->pluck('id')->all();

        if (! in_array($target->id, $chainIds, true) || $target->id === $instance->current_org_unit_id) {
            throw ValidationException::withMessages([
                'target_org_unit_id' => 'Target must be a unit below the current reviewer in this item\'s chain.',
            ]);
        }
    }

    /**
     * All units from origin up to (but not including) the current unit.
     */
    public static function chainFrom(OrgUnit $origin, OrgUnit $current): array
    {
        $chain = [];
        $cursor = $origin;
        $visited = [];
        while ($cursor && ! isset($visited[$cursor->id])) {
            $visited[$cursor->id] = true;
            if ($cursor->id !== $current->id) {
                $chain[] = $cursor;
            }
            $cursor = $cursor->parent;
        }
        return $chain;
    }

    protected static function actionLabel(string $action): string
    {
        return match ($action) {
            self::ACTION_ORIGINATED => 'Originated',
            self::ACTION_APPROVED => 'Approved',
            self::ACTION_ESCALATED => 'Escalated',
            self::ACTION_SENT_BACK => 'Sent back for clarification',
            self::ACTION_RESUBMITTED => 'Resubmitted',
            self::ACTION_REJECTED => 'Rejected',
            self::ACTION_AUTO_ESCALATED => 'Auto-escalated',
            self::ACTION_SENT_BACK_TIMEOUT => 'Clarification timeout — returned',
            default => ucfirst($action),
        };
    }

    /**
     * Human-readable summary for any Approvable Entity. Extend per module.
     */
    protected static function entitySummary(string $entityType, string $entityId): array
    {
        return match ($entityType) {
            'vendor_payout' => FinanceApprovalBridge::summary('vendor_payout', $entityId),
            'invoice' => FinanceApprovalBridge::summary('invoice', $entityId),
            'recovery' => FinanceApprovalBridge::summary('recovery', $entityId),
            'gst_filing' => FinanceApprovalBridge::summary('gst_filing', $entityId),
            'rider_payout' => RiderPayoutApprovalBridge::summary($entityId),
            'kyc_verification' => RiderKycApprovalBridge::summary('kyc_verification', $entityId),
            'sponsored_listing' => AccountManagementApprovalBridge::summary('sponsored_listing', $entityId),
            'offer' => AccountManagementApprovalBridge::summary('offer', $entityId),
            'restaurant_assignment' => AccountManagementApprovalBridge::summary('restaurant_assignment', $entityId),
            'wa_placement' => AccountManagementApprovalBridge::summary('wa_placement', $entityId),
            'complaint_refund' => SupportApprovalBridge::summary('complaint_refund', $entityId),
            'food_waste_claim' => FinanceApprovalBridge::summaryFoodWasteClaim($entityId),
            default => ['type' => $entityType, 'id' => $entityId, 'label' => $entityType . ' #' . substr($entityId, 0, 8)],
        };
    }

    protected static function runFinalizer(ApprovalWorkflowInstance $instance): void
    {
        $finalizer = self::$finalizers[$instance->entity_type] ?? null;
        if ($finalizer) {
            call_user_func($finalizer, $instance);
        }
    }

    /**
     * SLA breach / at-risk dashboard (§8).
     */
    public static function slaDashboard(array $filters = []): array
    {
        $query = ApprovalWorkflowInstance::with(['currentOrgUnit', 'currentOrgRole.assignedUser', 'currentOrgRole.backupUser'])
            ->whereIn('status', [self::STATUS_UNDER_REVIEW, self::STATUS_SENT_BACK]);

        if (! empty($filters['department'])) {
            $query->where('department', $filters['department']);
        }
        if (! empty($filters['breached_only'])) {
            $query->where('sla_due_at', '<', Carbon::now());
        } else {
            $query->whereNotNull('sla_due_at')->orderBy('sla_due_at');
        }

        $now = Carbon::now();
        return $query->limit(100)->get()->map(function ($i) use ($now) {
            $landing = self::landInstance($i);
            return self::decorate($i) + [
                'breached' => $i->sla_due_at ? $i->sla_due_at->isPast() : false,
                'minutes_remaining' => $i->sla_due_at ? (int) ceil($i->sla_due_at->diffInMinutes($now)) : null,
            ];
        })->all();
    }

    public static function registerFinalizer(string $entityType, callable $finalizer): void
    {
        self::$finalizers[$entityType] = $finalizer;
    }
}
