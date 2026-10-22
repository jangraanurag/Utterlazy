<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalThresholdConfig;
use App\Models\ApprovalWorkflowInstance;
use App\Models\OrgRole;
use App\Models\OrgUnit;
use App\Models\AccountManagerAssignment;
use App\Models\Rider;
use App\Models\RiderPayoutCycle;
use App\Models\SponsoredListing;
use App\Models\Offer;
use App\Models\Complaint;
use App\Models\GstLiabilityRegister;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Recovery;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\VendorPayment;
use App\Services\AccountManagementApprovalBridge;
use App\Services\ApprovalWorkflowEngine;
use App\Services\AuditLogService;
use App\Services\FinanceApprovalBridge;
use App\Services\RiderPayoutApprovalBridge;
use App\Services\RiderKycApprovalBridge;
use App\Services\SupportApprovalBridge;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrgApprovalController extends Controller
{
    // ─── Org chart builder (§8) ───

    public function orgTree(Request $request)
    {
        $department = $request->query('department');
        $query = OrgUnit::with(['roles.assignedUser', 'roles.backupUser']);
        if ($department) {
            $query->where('department', $department);
        }
        $units = $query->get();

        $nodeById = [];
        foreach ($units as $unit) {
            $nodeById[$unit->id] = [
                'id' => $unit->id,
                'name' => $unit->name,
                'department' => $unit->department,
                'unit_type' => $unit->unit_type,
                'geography_scope' => $unit->geography_scope,
                'is_active' => $unit->is_active,
                'depth' => ApprovalWorkflowEngine::treeDepth($unit),
                'roles' => $unit->roles->map(fn ($r) => [
                    'id' => $r->id,
                    'title' => $r->title,
                    'assigned_user' => $r->assignedUser ? ['id' => $r->assignedUser->id, 'name' => $r->assignedUser->name] : null,
                    'backup_user' => $r->backupUser ? ['id' => $r->backupUser->id, 'name' => $r->backupUser->name] : null,
                    'is_active' => $r->is_active,
                ])->all(),
                'children' => [],
            ];
        }

        $tree = [];
        foreach ($units as $unit) {
            $parentId = $unit->parent_org_unit_id;
            if ($parentId && isset($nodeById[$parentId])) {
                $nodeById[$parentId]['children'][] = &$nodeById[$unit->id];
            } else {
                $tree[] = &$nodeById[$unit->id];
            }
        }

        return response()->json($tree);
    }

    public function storeOrgUnit(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'department' => 'required|string|max:50',
            'unit_type' => 'required|string|max:50',
            'parent_org_unit_id' => 'nullable|exists:org_units,id',
            'geography_scope' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $unit = OrgUnit::create($data);
        AuditLogService::log(
            'org_unit_created',
            'approvals',
            'org_unit',
            (string) $unit->id,
            null,
            $unit->only(['department', 'unit_type', 'name', 'parent_org_unit_id', 'geography_scope', 'is_active'])
        );

        return response()->json($unit->load('roles'), 201);
    }

    public function updateOrgUnit(Request $request, OrgUnit $unit)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:191',
            'department' => 'sometimes|string|max:50',
            'unit_type' => 'sometimes|string|max:50',
            'parent_org_unit_id' => 'nullable|exists:org_units,id',
            'geography_scope' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        if (($data['parent_org_unit_id'] ?? null) === $unit->id) {
            throw ValidationException::withMessages(['parent_org_unit_id' => 'A unit cannot be its own parent.']);
        }

        $unit->update($data);
        AuditLogService::log(
            'org_unit_updated',
            'approvals',
            'org_unit',
            (string) $unit->id,
            $unit->getOriginal(),
            $unit->only(['department', 'unit_type', 'name', 'parent_org_unit_id', 'geography_scope', 'is_active'])
        );

        return response()->json($unit->load('roles'));
    }

    public function deleteOrgUnit(OrgUnit $unit)
    {
        if ($unit->children()->exists()) {
            throw ValidationException::withMessages(['unit' => 'Delete child units first — this unit has ' . $unit->children()->count() . ' children.']);
        }
        if ($unit->roles()->exists()) {
            throw ValidationException::withMessages(['unit' => 'Remove roles from this unit before deleting it.']);
        }

        $unit->delete();
        AuditLogService::log(
            'org_unit_deleted',
            'approvals',
            'org_unit',
            (string) $unit->id,
            $unit->only(['department', 'unit_type', 'name', 'parent_org_unit_id', 'geography_scope', 'is_active']),
            null
        );

        return response()->json(['ok' => true]);
    }

    public function storeOrgRole(Request $request, OrgUnit $unit)
    {
        $data = $request->validate([
            'title' => 'required|string|max:191',
            'assigned_user_id' => 'nullable|exists:users,id',
            'backup_user_id' => 'nullable|exists:users,id',
            'is_active' => 'nullable|boolean',
        ]);

        $role = $unit->roles()->create($data);
        AuditLogService::log(
            'org_role_created',
            'approvals',
            'org_role',
            (string) $role->id,
            null,
            $role->only(['org_unit_id', 'title', 'assigned_user_id', 'backup_user_id', 'is_active'])
        );

        return response()->json($role->load(['assignedUser', 'backupUser']), 201);
    }

    public function updateOrgRole(Request $request, OrgRole $role)
    {
        $data = $request->validate([
            'title' => 'sometimes|string|max:191',
            'assigned_user_id' => 'nullable|exists:users,id',
            'backup_user_id' => 'nullable|exists:users,id',
            'is_active' => 'nullable|boolean',
        ]);

        $role->update($data);
        AuditLogService::log(
            'org_role_updated',
            'approvals',
            'org_role',
            (string) $role->id,
            $role->getOriginal(),
            $role->only(['org_unit_id', 'title', 'assigned_user_id', 'backup_user_id', 'is_active'])
        );

        return response()->json($role->load(['assignedUser', 'backupUser']));
    }

    public function deleteOrgRole(OrgRole $role)
    {
        AuditLogService::log(
            'org_role_deleted',
            'approvals',
            'org_role',
            (string) $role->id,
            $role->only(['org_unit_id', 'title', 'assigned_user_id', 'backup_user_id', 'is_active']),
            null
        );
        $role->delete();

        return response()->json(['ok' => true]);
    }

    // ─── DOA threshold matrix (§4) ───

    public function thresholdIndex(Request $request)
    {
        $query = ApprovalThresholdConfig::query();
        if ($request->query('department')) {
            $query->where('department', $request->query('department'));
        }
        if ($request->query('entity_type')) {
            $query->where('entity_type', $request->query('entity_type'));
        }

        return response()->json($query->orderBy('department')->orderBy('unit_type')->orderBy('entity_type')->get());
    }

    public function storeThreshold(Request $request)
    {
        $data = $request->validate([
            'department' => 'required|string|max:50',
            'unit_type' => 'required|string|max:50',
            'entity_type' => 'required|string|max:50',
            'max_amount' => 'nullable|numeric|min:0',
            'sla_hours' => 'required|integer|min:1',
            'geography_scope' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $config = ApprovalThresholdConfig::create($data);
        AuditLogService::log(
            'threshold_created',
            'approvals',
            'approval_threshold_config',
            (string) $config->id,
            null,
            $config->only(['department', 'unit_type', 'entity_type', 'max_amount', 'sla_hours', 'is_active'])
        );

        return response()->json($config, 201);
    }

    public function updateThreshold(Request $request, ApprovalThresholdConfig $config)
    {
        $data = $request->validate([
            'max_amount' => 'nullable|numeric|min:0',
            'sla_hours' => 'sometimes|integer|min:1',
            'geography_scope' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'department' => 'sometimes|string|max:50',
            'unit_type' => 'sometimes|string|max:50',
            'entity_type' => 'sometimes|string|max:50',
        ]);

        $old = $config->only(['department', 'unit_type', 'entity_type', 'max_amount', 'sla_hours', 'is_active']);
        $config->update($data);
        AuditLogService::log(
            'threshold_updated',
            'approvals',
            'approval_threshold_config',
            (string) $config->id,
            $old,
            $config->only(['department', 'unit_type', 'entity_type', 'max_amount', 'sla_hours', 'is_active'])
        );

        return response()->json($config);
    }

    public function deleteThreshold(ApprovalThresholdConfig $config)
    {
        AuditLogService::log(
            'threshold_deleted',
            'approvals',
            'approval_threshold_config',
            (string) $config->id,
            $config->only(['department', 'unit_type', 'entity_type', 'max_amount', 'sla_hours', 'is_active']),
            null
        );
        $config->delete();

        return response()->json(['ok' => true]);
    }

    public function thresholdAudit(Request $request)
    {
        $logs = \App\Models\AdminAuditLog::where('module', 'approvals')
            ->whereIn('action', ['threshold_created', 'threshold_updated', 'threshold_deleted'])
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return response()->json($logs);
    }

    // ─── Approval queue & actions (§5, §8) ───

    public function myQueue(Request $request)
    {
        return response()->json([
            'items' => ApprovalWorkflowEngine::queueForUser($request->user(), $request->only(['status', 'department', 'entity_type'])),
        ]);
    }

    public function showWorkflow(Request $request, ApprovalWorkflowInstance $workflow)
    {
        return response()->json(ApprovalWorkflowEngine::timeline($workflow));
    }

    public function approve(Request $request, ApprovalWorkflowInstance $workflow)
    {
        $comment = $request->validate(['comment' => 'nullable|string|max:2000'])['comment'] ?? '';
        return response()->json(ApprovalWorkflowEngine::decorate(ApprovalWorkflowEngine::approve($workflow, $request->user(), $comment)));
    }

    public function escalate(Request $request, ApprovalWorkflowInstance $workflow)
    {
        $comment = $request->validate(['comment' => 'nullable|string|max:2000'])['comment'] ?? '';
        return response()->json(ApprovalWorkflowEngine::decorate(ApprovalWorkflowEngine::escalate($workflow, $request->user(), $comment)));
    }

    public function sendBack(Request $request, ApprovalWorkflowInstance $workflow)
    {
        $data = $request->validate([
            'target_org_unit_id' => 'required|exists:org_units,id',
            'comment' => 'required|string|min:5|max:2000',
        ]);
        return response()->json(ApprovalWorkflowEngine::decorate(ApprovalWorkflowEngine::sendBack($workflow, $request->user(), $data['target_org_unit_id'], $data['comment'])));
    }

    public function resubmit(Request $request, ApprovalWorkflowInstance $workflow)
    {
        $comment = $request->validate(['comment' => 'nullable|string|max:2000'])['comment'] ?? '';
        return response()->json(ApprovalWorkflowEngine::decorate(ApprovalWorkflowEngine::resubmit($workflow, $request->user(), $comment)));
    }

    public function reject(Request $request, ApprovalWorkflowInstance $workflow)
    {
        $data = $request->validate(['comment' => 'required|string|min:3|max:2000']);
        return response()->json(ApprovalWorkflowEngine::decorate(ApprovalWorkflowEngine::reject($workflow, $request->user(), $data['comment'])));
    }

    public function slaDashboard(Request $request)
    {
        return response()->json([
            'items' => ApprovalWorkflowEngine::slaDashboard($request->only(['department', 'breached_only'])),
        ]);
    }

    public function chainOptions(Request $request, ApprovalWorkflowInstance $workflow)
    {
        $current = $workflow->currentOrgUnit;
        $options = $current ? ApprovalWorkflowEngine::chainFrom($workflow->originOrgUnit, $current) : [];

        return response()->json(array_map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'unit_type' => $u->unit_type,
        ], $options));
    }

    // ─── Admin users (for role assignment) ───

    public function adminUsers(Request $request)
    {
        $users = \App\Models\User::where('is_admin', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'admin_role']);

        return response()->json($users);
    }

    // ─── Finance reference implementation (§6) ───

    public function originateVendorPayout(Request $request, VendorPayment $payment)
    {
        $data = $request->validate([
            'is_exception' => 'nullable|boolean',
        ]);

        $instance = FinanceApprovalBridge::originateVendorPayout(
            $payment,
            $request->user(),
            $data['is_exception'] ?? false
        );

        return response()->json(ApprovalWorkflowEngine::decorate($instance), 201);
    }

    public function pendingVendorPayouts(Request $request)
    {
        $payments = VendorPayment::with('restaurant:id,name,state,district')
            ->whereIn('status', ['pending', 'generated'])
            ->whereNull('approved_at')
            ->orderBy('week_start', 'desc')
            ->limit(50)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'restaurant' => $p->restaurant?->name,
                'state' => $p->restaurant?->state,
                'district' => $p->restaurant?->district,
                'week' => $p->payment_period,
                'net_payable' => (float) $p->net_payable,
                'status' => $p->status,
            ]);

        return response()->json($payments);
    }

    // ─── Rider Ops (§6) ───

    public function originateRiderPayout(Request $request, RiderPayoutCycle $cycle)
    {
        $data = $request->validate([
            'is_exception' => 'nullable|boolean',
        ]);

        $instance = RiderPayoutApprovalBridge::originateRiderPayout(
            $cycle,
            $request->user(),
            $data['is_exception'] ?? false
        );

        return response()->json(ApprovalWorkflowEngine::decorate($instance), 201);
    }

    public function pendingRiderPayoutCycles(Request $request)
    {
        $cycles = RiderPayoutCycle::with('rider:id,name,phone,state,district')
            ->whereIn('status', ['generated', 'pending_payment'])
            ->orderBy('cycle_date', 'desc')
            ->limit(50)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'rider' => $c->rider?->name,
                'rider_phone' => $c->rider?->phone,
                'state' => $c->rider?->state,
                'district' => $c->rider?->district,
                'cycle_date' => $c->cycle_date?->toDateString(),
                'net_payable' => (float) $c->net_payable,
                'orders' => (int) $c->total_orders,
                'status' => $c->status,
            ]);

        return response()->json($cycles);
    }

    // ─── Rider Ops: KYC verification (§6) ───

    public function originateKycVerification(Request $request, Rider $rider)
    {
        $data = $request->validate([
            'is_exception' => 'nullable|boolean',
        ]);

        if ($rider->is_verified) {
            return response()->json(['error' => 'This rider is already verified'], 422);
        }

        $instance = RiderKycApprovalBridge::originateKycVerification(
            $rider,
            $request->user(),
            $data['is_exception'] ?? false
        );

        return response()->json(ApprovalWorkflowEngine::decorate($instance), 201);
    }

    public function pendingKycVerifications(Request $request)
    {
        $riders = Rider::with('documents')
            ->where('is_verified', false)
            ->whereHas('documents')
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'phone' => $r->phone,
                'state' => $r->state,
                'district' => $r->district,
                'vehicle' => ($r->vehicle_type ?? '') . ' ' . ($r->vehicle_number ?? ''),
                'documents' => (int) $r->documents->count(),
                'doc_statuses' => $r->documents->groupBy('document_type')->map(fn ($g) => $g->first()->status)->toArray(),
                'is_verified' => (bool) $r->is_verified,
            ]);

        return response()->json($riders);
    }

    // ─── Account Management (§6) ───

    public function originateSponsoredListing(Request $request, SponsoredListing $listing)
    {
        $data = $request->validate([
            'is_exception' => 'nullable|boolean',
        ]);

        $instance = AccountManagementApprovalBridge::originateSponsoredListing(
            $listing,
            $request->user(),
            $data['is_exception'] ?? false
        );

        return response()->json(ApprovalWorkflowEngine::decorate($instance), 201);
    }

    public function originateOffer(Request $request, Offer $offer)
    {
        $data = $request->validate([
            'is_exception' => 'nullable|boolean',
        ]);

        $instance = AccountManagementApprovalBridge::originateOffer(
            $offer,
            $request->user(),
            $data['is_exception'] ?? false
        );

        return response()->json(ApprovalWorkflowEngine::decorate($instance), 201);
    }

    public function pendingSponsoredListings(Request $request)
    {
        $listings = SponsoredListing::with('restaurant:id,name,state,district')
            ->where(function ($q) {
                $q->where('approval_status', 'pending')->orWhereNull('approval_status');
            })
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'restaurant' => $a->restaurant?->name,
                'state' => $a->restaurant?->state,
                'district' => $a->restaurant?->district,
                'ad_type' => $a->ad_type,
                'daily_budget' => (float) $a->daily_budget,
                'weekly_budget' => (float) $a->weekly_budget,
                'route_amount' => (float) ($a->weekly_budget ?: $a->daily_budget ?: $a->bid_amount),
                'approval_status' => $a->approval_status ?? 'pending',
                'created_at' => $a->created_at,
            ]);

        return response()->json($listings);
    }

    public function pendingOffers(Request $request)
    {
        $offers = Offer::with('restaurant:id,name,state,district')
            ->where(function ($q) {
                $q->where('approval_status', 'pending')->orWhereNull('approval_status');
            })
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'restaurant' => $o->restaurant?->name,
                'state' => $o->restaurant?->state,
                'district' => $o->restaurant?->district,
                'title' => $o->title,
                'type' => $o->type,
                'discount_value' => (float) $o->discount_value,
                'max_discount' => (float) $o->max_discount,
                'budget' => (float) $o->budget,
                'route_amount' => (float) ($o->budget ?: $o->max_discount ?: $o->discount_value),
                'approval_status' => $o->approval_status ?? 'pending',
                'created_at' => $o->created_at,
            ]);

        return response()->json($offers);
    }

    // ─── Account Management: restaurant→AM assignment (§6) ───

    public function pendingRestaurantAssignments(Request $request)
    {
        $assignedIds = AccountManagerAssignment::where('is_active', true)->pluck('restaurant_id');

        $restaurants = Restaurant::whereNotIn('id', $assignedIds)
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'state', 'district'])
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'state' => $r->state,
                'district' => $r->district,
            ]);

        $ams = User::where('is_admin', true)->where('admin_role', 'account_manager')
            ->orderBy('name')->get(['id', 'name', 'email']);

        return response()->json(['restaurants' => $restaurants, 'account_managers' => $ams]);
    }

    public function originateRestaurantAssignment(Request $request)
    {
        $data = $request->validate([
            'restaurant_id' => 'required|exists:restaurants,id',
            'account_manager_id' => 'required|exists:users,id',
        ]);

        $restaurant = Restaurant::findOrFail($data['restaurant_id']);
        $am = User::findOrFail($data['account_manager_id']);

        if (AccountManagerAssignment::where('restaurant_id', $restaurant->id)->where('is_active', true)->exists()) {
            return response()->json(['error' => 'This restaurant already has an active AM'], 422);
        }

        $instance = AccountManagementApprovalBridge::originateRestaurantAssignment(
            $restaurant,
            $am,
            $request->user()
        );

        return response()->json(ApprovalWorkflowEngine::decorate($instance), 201);
    }

    // ─── Support (§6) ───

    public function originateComplaintRefund(Request $request, Complaint $complaint)
    {
        $data = $request->validate([
            'is_exception' => 'nullable|boolean',
        ]);

        if ($complaint->refund_processed || $complaint->status === 'closed') {
            return response()->json(['error' => 'Refund already processed or complaint closed'], 422);
        }
        if ((float) $complaint->refund_amount <= 0) {
            return response()->json(['error' => 'No refund amount set on this complaint'], 422);
        }

        $instance = SupportApprovalBridge::originateComplaintRefund(
            $complaint,
            $request->user(),
            $data['is_exception'] ?? false
        );

        return response()->json(ApprovalWorkflowEngine::decorate($instance), 201);
    }

    public function pendingRefundComplaints(Request $request)
    {
        $complaints = Complaint::with(['restaurant:id,name,state,district', 'user:id,name'])
            ->where('refund_amount', '>', 0)
            ->where('refund_processed', false)
            ->where('status', '!=', 'closed')
            ->orderBy('updated_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn ($c) => [
                'id' => (string) $c->id,
                'order_id' => $c->order_id,
                'customer' => $c->user?->name,
                'restaurant' => $c->restaurant?->name,
                'state' => $c->restaurant?->state,
                'district' => $c->restaurant?->district,
                'reason' => $c->reason,
                'refund_amount' => (float) $c->refund_amount,
                'status' => $c->status,
                'updated_at' => $c->updated_at,
            ]);

        return response()->json($complaints);
    }

    // ─── Finance: invoices & recoveries (§6) ───

    public function originateInvoice(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'is_exception' => 'nullable|boolean',
        ]);

        if ($invoice->status === 'cancelled') {
            return response()->json(['error' => 'Cancelled invoices cannot be routed'], 422);
        }

        $instance = FinanceApprovalBridge::originateInvoice(
            $invoice,
            $request->user(),
            $data['is_exception'] ?? false
        );

        return response()->json(ApprovalWorkflowEngine::decorate($instance), 201);
    }

    public function originateRecovery(Request $request, Recovery $recovery)
    {
        $data = $request->validate([
            'is_exception' => 'nullable|boolean',
        ]);

        if ($recovery->status === 'waived') {
            return response()->json(['error' => 'Waived recoveries cannot be routed'], 422);
        }

        $instance = FinanceApprovalBridge::originateRecovery(
            $recovery,
            $request->user(),
            $data['is_exception'] ?? false
        );

        return response()->json(ApprovalWorkflowEngine::decorate($instance), 201);
    }

    public function pendingInvoices(Request $request)
    {
        $invoices = Invoice::with('restaurant:id,name,state,district')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'restaurant' => $i->restaurant?->name,
                'state' => $i->restaurant?->state,
                'district' => $i->restaurant?->district,
                'type' => $i->type,
                'invoice_number' => $i->invoice_number,
                'total_amount' => (float) ($i->total_amount ?: $i->amount),
                'status' => $i->status,
                'created_at' => $i->created_at,
            ]);

        return response()->json($invoices);
    }

    public function pendingRecoveries(Request $request)
    {
        $recoveries = Recovery::with('restaurant:id,name,state,district')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'restaurant' => $r->restaurant?->name,
                'state' => $r->restaurant?->state,
                'district' => $r->restaurant?->district,
                'reference' => $r->reference_type,
                'amount' => (float) $r->amount,
                'status' => $r->status,
                'created_at' => $r->created_at,
            ]);

        return response()->json($recoveries);
    }

    // ─── Finance: GST filings (§6) ───

    public function gstFilings(Request $request)
    {
        $query = GstLiabilityRegister::query();
        if ($request->query('state')) {
            $query->where('state', $request->query('state'));
        }
        if ($request->query('period')) {
            $query->where('filing_period', $request->query('period'));
        }
        if ($request->query('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json(
            $query->orderByDesc('filing_period')->orderBy('state')->get()
        );
    }

    /**
     * Compute monthly GST liability per state from delivered orders and
     * upsert GstLiabilityRegister rows (fully scalable: any state, any period).
     */
    public function computeGstLiability(Request $request)
    {
        $data = $request->validate([
            'year' => 'required|integer|min:2020|max:2099',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $period = sprintf('%04d-%02d', $data['year'], $data['month']);
        $start = $period . '-01';
        $end = date('Y-m-t', strtotime($start));

        $rows = Order::whereBetween('created_at', [$start . ' 00:00:00', $end . ' 23:59:59'])
            ->whereIn('status', ['delivered', 'completed'])
            ->whereNotNull('restaurant_id')
            ->with('restaurant:id,state')
            ->get()
            ->groupBy(fn ($o) => $o->restaurant?->state ?? 'UNKNOWN')
            ->map(function ($orders, $state) use ($period) {
                $totalGst = round($orders->sum('tax_amount'), 2);
                $totalTaxable = round($orders->sum(function ($o) {
                    return $o->subtotal - $o->vendor_discount_amount - ($o->coupon_discount_amount ?? 0);
                }), 2);

                $stateCol = $state === 'UNKNOWN' ? null : $state;
                $existing = GstLiabilityRegister::where('state', $stateCol)->where('filing_period', $period)->first();
                $alreadyFiled = $existing && $existing->status === 'filed';

                $filing = GstLiabilityRegister::updateOrCreate(
                    ['state' => $stateCol, 'filing_period' => $period],
                    [
                        'total_gst_collected' => $totalGst,
                        'total_gst_deposited' => $alreadyFiled ? $existing->total_gst_collected : 0,
                        'status' => $alreadyFiled ? 'filed' : 'pending',
                    ]
                );

                return [
                    'id' => $filing->id,
                    'state' => $stateCol,
                    'filing_period' => $period,
                    'total_taxable' => $totalTaxable,
                    'total_gst_collected' => $totalGst,
                    'total_gst_deposited' => (float) $filing->total_gst_deposited,
                    'status' => $filing->status,
                ];
            })
            ->values();

        return response()->json($rows);
    }

    public function originateGstFiling(Request $request, GstLiabilityRegister $filing)
    {
        $data = $request->validate([
            'is_exception' => 'nullable|boolean',
        ]);

        if ($filing->status === 'filed') {
            return response()->json(['error' => 'This period is already filed'], 422);
        }

        $instance = FinanceApprovalBridge::originateGstFiling(
            $filing,
            $request->user(),
            $data['is_exception'] ?? false
        );

        return response()->json(ApprovalWorkflowEngine::decorate($instance), 201);
    }
}
