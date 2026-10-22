<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\OrgUnit;
use App\Models\OrgRole;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\VendorPayment;
use App\Services\ApprovalWorkflowEngine;
use App\Services\FinanceApprovalBridge;

$pass = 0;
$fail = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) { $pass++; echo "  PASS  {$label}\n"; }
    else { $fail++; echo "  FAIL  {$label} — {$detail}\n"; }
}

echo "=== Setup org tree: finance, deep branch (with Region) + shallow branch ===\n";

$national = OrgUnit::create(['department' => 'finance', 'unit_type' => 'national', 'name' => 'Finance HQ (CAO)', 'geography_scope' => null]);
$haryana = OrgUnit::create(['department' => 'finance', 'unit_type' => 'state', 'name' => 'Haryana State Office', 'parent_org_unit_id' => $national->id, 'geography_scope' => [['state' => 'Haryana']]]);
$bhiwaniDist = OrgUnit::create(['department' => 'finance', 'unit_type' => 'district', 'name' => 'Bhiwani District Office', 'parent_org_unit_id' => $haryana->id, 'geography_scope' => [['state' => 'Haryana', 'district' => 'Bhiwani']]]);
$bhiwaniRegion = OrgUnit::create(['department' => 'finance', 'unit_type' => 'region', 'name' => 'Bhiwani North Region', 'parent_org_unit_id' => $bhiwaniDist->id, 'geography_scope' => [['state' => 'Haryana', 'district' => 'Bhiwani', 'pincodes' => ['127021']]]]);

// Shallow branch: Delhi → Delhi District (no region layer)
$delhi = OrgUnit::create(['department' => 'finance', 'unit_type' => 'state', 'name' => 'Delhi State Office', 'parent_org_unit_id' => $national->id, 'geography_scope' => [['state' => 'Delhi']]]);
$delhiDist = OrgUnit::create(['department' => 'finance', 'unit_type' => 'district', 'name' => 'Delhi District Office', 'parent_org_unit_id' => $delhi->id, 'geography_scope' => [['state' => 'Delhi', 'district' => 'Delhi']]]);

check('variable-depth tree works', $bhiwaniRegion->id && $delhiDist->id);

echo "=== Threshold configs (order-of-magnitude ladder) ===\n";
\App\Models\ApprovalThresholdConfig::create(['department' => 'finance', 'unit_type' => 'region', 'entity_type' => 'vendor_payout', 'max_amount' => 25000, 'sla_hours' => 24]);
\App\Models\ApprovalThresholdConfig::create(['department' => 'finance', 'unit_type' => 'district', 'entity_type' => 'vendor_payout', 'max_amount' => 250000, 'sla_hours' => 24]);
\App\Models\ApprovalThresholdConfig::create(['department' => 'finance', 'unit_type' => 'state', 'entity_type' => 'vendor_payout', 'max_amount' => 2500000, 'sla_hours' => 12]);
\App\Models\ApprovalThresholdConfig::create(['department' => 'finance', 'unit_type' => 'national', 'entity_type' => 'vendor_payout', 'max_amount' => null, 'sla_hours' => 8]);

echo "=== Users & roles ===\n";
$cao = User::create(['name' => 'CAO', 'email' => 'cao@test.com', 'password' => 'x', 'is_admin' => true, 'admin_role' => 'super_admin']);
$stateOfficer = User::create(['name' => 'State Officer', 'email' => 'so@test.com', 'password' => 'x', 'is_admin' => true, 'admin_role' => 'finance_manager']);
$distOfficer = User::create(['name' => 'District Officer', 'email' => 'do@test.com', 'password' => 'x', 'is_admin' => true, 'admin_role' => 'finance_manager']);
$regOfficer = User::create(['name' => 'Region Officer', 'email' => 'ro@test.com', 'password' => 'x', 'is_admin' => true, 'admin_role' => 'finance_manager']);
$delhiOfficer = User::create(['name' => 'Delhi Officer', 'email' => 'delho@test.com', 'password' => 'x', 'is_admin' => true, 'admin_role' => 'finance_manager']);

OrgRole::create(['org_unit_id' => $national->id, 'title' => 'Chief Accounts Officer', 'assigned_user_id' => $cao->id]);
OrgRole::create(['org_unit_id' => $haryana->id, 'title' => 'State Account Officer', 'assigned_user_id' => $stateOfficer->id]);
OrgRole::create(['org_unit_id' => $bhiwaniDist->id, 'title' => 'District Account Officer', 'assigned_user_id' => $distOfficer->id]);
OrgRole::create(['org_unit_id' => $bhiwaniRegion->id, 'title' => 'Regional Account Officer', 'assigned_user_id' => $regOfficer->id]);
OrgRole::create(['org_unit_id' => $delhiDist->id, 'title' => 'District Account Officer', 'assigned_user_id' => $delhiOfficer->id]);

echo "=== Test 1: geo routing — Bhiwani restaurant originates at REGION (deepest match) ===\n";
$rest = Restaurant::create(['name' => 'Saffron Palace', 'address' => 'Circular Road, Bhiwani', 'state' => 'Haryana', 'district' => 'Bhiwani']);
$pay = VendorPayment::create(['restaurant_id' => $rest->id, 'net_payable' => 5000, 'status' => 'pending', 'payment_period' => 'W1']);
$inst = FinanceApprovalBridge::originateVendorPayout($pay, $distOfficer);
check('originates at region (deepest unit)', $inst->current_org_unit_id === $bhiwaniRegion->id, $inst->currentOrgUnit->name ?? '?');
check('origin step recorded', $inst->steps()->where('action', 'originated')->exists());

echo "=== Test 2: within threshold → direct approve (fast path) ===\n";
$d = ApprovalWorkflowEngine::decorate($inst);
check('approvable at region (₹5000 ≤ ₹25000)', $d['approvable']);
ApprovalWorkflowEngine::approve($inst, $regOfficer, 'Amount verified');
$inst->refresh();
check('approved', $inst->status === 'approved');
check('audit step written', $inst->steps()->where('action', 'approved')->exists());
check('finalizer ran — payout approved', VendorPayment::find($pay->id)->status === 'approved');

echo "=== Test 3: above threshold → escalate chain, approve at CAO ===\n";
$pay2 = VendorPayment::create(['restaurant_id' => $rest->id, 'net_payable' => 500000, 'status' => 'pending', 'payment_period' => 'W2']);
$inst2 = FinanceApprovalBridge::originateVendorPayout($pay2, $distOfficer);
check('originates at region', $inst2->currentOrgUnit->id === $bhiwaniRegion->id);
$d2 = ApprovalWorkflowEngine::decorate($inst2);
check('NOT approvable at region (₹5L > ₹25K)', ! $d2['approvable']);
// region cannot approve — engine must refuse
$refused = false;
try { ApprovalWorkflowEngine::approve($inst2, $regOfficer, 'try'); } catch (\Exception $e) { $refused = true; }
check('engine refuses over-threshold approve', $refused);

ApprovalWorkflowEngine::escalate($inst2, $regOfficer, 'Above threshold');
$inst2->refresh();
check('escalated to district', $inst2->currentOrgUnit->id === $bhiwaniDist->id, $inst2->currentOrgUnit->name ?? '?');
ApprovalWorkflowEngine::escalate($inst2, $distOfficer, 'Above threshold');
$inst2->refresh();
check('escalated to state', $inst2->currentOrgUnit->id === $haryana->id);
ApprovalWorkflowEngine::escalate($inst2, $stateOfficer, 'Above threshold');
$inst2->refresh();
check('escalated to national/CAO', $inst2->currentOrgUnit->id === $national->id);
check('CAO approve allowed (null threshold = unlimited)', ApprovalWorkflowEngine::decorate($inst2)['approvable']);
ApprovalWorkflowEngine::approve($inst2, $cao, 'Approved at CAO');
$inst2->refresh();
check('approved at CAO', $inst2->status === 'approved');

echo "=== Test 4: shallow branch — Delhi district directly under state, no region ===\n";
$restD = Restaurant::create(['name' => 'Delhi Darbar', 'address' => 'Karol Bagh', 'state' => 'Delhi', 'district' => 'Delhi']);
$pay3 = VendorPayment::create(['restaurant_id' => $restD->id, 'net_payable' => 3000, 'status' => 'pending', 'payment_period' => 'W3']);
$inst3 = FinanceApprovalBridge::originateVendorPayout($pay3, $delhiOfficer);
check('originates at Delhi district (no region layer)', $inst3->currentOrgUnit->id === $delhiDist->id, $inst3->currentOrgUnit->name ?? '?');
ApprovalWorkflowEngine::approve($inst3, $delhiOfficer, 'ok');
$inst3->refresh();
check('approved at Delhi district directly', $inst3->status === 'approved');

echo "=== Test 5: exception flag → must escalate even within threshold ===\n";
$pay4 = VendorPayment::create(['id' => 'p4', 'restaurant_id' => 'r1', 'net_payable' => 1000, 'status' => 'pending', 'payment_period' => 'W9', 'payment_period' => 'W7', 'payment_period' => 'W4']);
$inst4 = FinanceApprovalBridge::originateVendorPayout($pay4, $distOfficer, isException: true);
check('exception NOT approvable at region', ! ApprovalWorkflowEngine::decorate($inst4)['approvable']);

echo "=== Test 6: send back to ANY lower level (region → district directly) ===\n";
$pay5 = VendorPayment::create(['restaurant_id' => $rest->id, 'net_payable' => 500000, 'status' => 'pending', 'payment_period' => 'W5']);
$inst5 = FinanceApprovalBridge::originateVendorPayout($pay5, $distOfficer);
ApprovalWorkflowEngine::escalate($inst5, $regOfficer, 'above threshold');
$inst5->refresh(); // now at district
ApprovalWorkflowEngine::sendBack($inst5, $distOfficer, $bhiwaniRegion->id, 'Verify the bank account details');
$inst5->refresh();
check('sent back to region (lower level)', $inst5->status === 'sent_back' && $inst5->currentOrgUnit->id === $bhiwaniRegion->id);
$noComment = false;
try { ApprovalWorkflowEngine::sendBack($inst5, $regOfficer, $bhiwaniDist->id, 'x'); } catch (\Illuminate\Validation\ValidationException $e) { $noComment = true; }
check('send-back requires comment (min 5 chars)', $noComment);
ApprovalWorkflowEngine::resubmit($inst5, $regOfficer, 'Bank details verified — resubmitting');
$inst5->refresh();
check('resubmitted to district (unit that sent it back)', $inst5->currentOrgUnit->id === $bhiwaniDist->id && $inst5->status === 'under_review');

echo "=== Test 7: reject ===\n";
$pay6 = VendorPayment::create(['restaurant_id' => $rest->id, 'net_payable' => 2000, 'status' => 'pending', 'payment_period' => 'W6']);
$inst6 = FinanceApprovalBridge::originateVendorPayout($pay6, $distOfficer);
ApprovalWorkflowEngine::reject($inst6, $regOfficer, 'Duplicate payout');
$inst6->refresh();
check('rejected + terminal', $inst6->status === 'rejected' && $inst6->completed_at !== null);

echo "=== Test 8: vacancy — role with no assigned user auto-escalates ===\n";
$up = OrgUnit::create(['department' => 'finance', 'unit_type' => 'region', 'name' => 'Bhiwani South Region (vacant)', 'parent_org_unit_id' => $bhiwaniDist->id, 'geography_scope' => [['state' => 'Haryana', 'district' => 'Bhiwani', 'pincodes' => ['127022']]]]);
OrgRole::create(['org_unit_id' => $up->id, 'title' => 'Regional Account Officer', 'assigned_user_id' => null, 'backup_user_id' => null]);
$pay7 = VendorPayment::create(['id' => 'p7', 'restaurant_id' => 'r1', 'net_payable' => 1000, 'status' => 'pending', 'payment_period' => 'W9', 'payment_period' => 'W7', 'payment_period' => 'W4']);
$inst7 = ApprovalWorkflowEngine::originate('finance', 'vendor_payout', 'p7', 1000, ['state' => 'Haryana', 'district' => 'Bhiwani', 'pincodes' => ['127022']], false, null, $distOfficer);
$inst7->refresh();
check('vacant region auto-escalated to district', $inst7->currentOrgUnit->id === $bhiwaniDist->id, $inst7->currentOrgUnit->name ?? '?');
check('auto-escalation audited', $inst7->steps()->where('action', 'auto_escalated')->where('metadata->reason', 'vacancy')->exists());

echo "=== Test 9: backup user handles when assignee unavailable ===\n";
$backup = User::create(['name' => 'Backup Officer', 'email' => 'bk@test.com', 'password' => 'x', 'is_admin' => true, 'admin_role' => 'finance_manager']);
OrgRole::where('org_unit_id', $delhiDist->id)->update(['assigned_user_id' => null, 'backup_user_id' => $backup->id]);
$pay8 = VendorPayment::create(['restaurant_id' => $restD->id, 'net_payable' => 3000000, 'status' => 'pending', 'payment_period' => 'W8']);
$inst8 = FinanceApprovalBridge::originateVendorPayout($pay8, $backup);
$inst8->refresh();
check('backup resolves reviewer', str_contains(ApprovalWorkflowEngine::decorate($inst8)['reviewer'] ?? '', 'Backup'));
ApprovalWorkflowEngine::escalate($inst8, $backup, 'above threshold');
$inst8->refresh();
check('backup can escalate', $inst8->currentOrgUnit->id === $national->id);

echo "=== Test 10: SLA auto-escalation ===\n";
$pay9 = VendorPayment::create(['restaurant_id' => $rest->id, 'net_payable' => 1000, 'status' => 'pending', 'payment_period' => 'W9', 'payment_period' => 'W7', 'payment_period' => 'W4']);
$inst9 = FinanceApprovalBridge::originateVendorPayout($pay9, $distOfficer);
$inst9->update(['sla_due_at' => now()->subHour()]);
$res = ApprovalWorkflowEngine::checkSlaTimeouts();
$inst9->refresh();
check('SLA timeout auto-escalated (region → district)', $inst9->currentOrgUnit->id === $bhiwaniDist->id, $inst9->currentOrgUnit->name ?? '?');
check('SLA step audited', $inst9->steps()->where('action', 'auto_escalated')->where('metadata->reason', 'sla_timeout')->exists());

echo "=== Test 11: queue scoping ===\n";
$queue = ApprovalWorkflowEngine::queueForUser($distOfficer);
$allMine = collect($queue)->every(fn ($i) => $i['current_org_unit'] !== null);
check('district officer sees queue items', count($queue) >= 1);

echo "=== Test 12: Rider Ops — rider payout cycle through the engine (§6) ===\n";
$riderOps = OrgUnit::create(['department' => 'rider_ops', 'unit_type' => 'national', 'name' => 'Rider Ops HQ']);
$riderOpsState = OrgUnit::create(['department' => 'rider_ops', 'unit_type' => 'state', 'name' => 'Rider Ops Haryana', 'parent_org_unit_id' => $riderOps->id, 'geography_scope' => [['state' => 'Haryana']]]);
$riderOpsDist = OrgUnit::create(['department' => 'rider_ops', 'unit_type' => 'district', 'name' => 'Rider Ops Bhiwani', 'parent_org_unit_id' => $riderOpsState->id, 'geography_scope' => [['state' => 'Haryana', 'district' => 'Bhiwani']]]);
OrgRole::create(['org_unit_id' => $riderOpsDist->id, 'title' => 'Rider Ops District Officer', 'assigned_user_id' => $distOfficer->id]);
OrgRole::create(['org_unit_id' => $riderOpsState->id, 'title' => 'Rider Ops State Officer', 'assigned_user_id' => $stateOfficer->id]);
\App\Models\ApprovalThresholdConfig::create(['department' => 'rider_ops', 'unit_type' => 'district', 'entity_type' => 'rider_payout', 'max_amount' => 50000, 'sla_hours' => 24]);
\App\Models\ApprovalThresholdConfig::create(['department' => 'rider_ops', 'unit_type' => 'state', 'entity_type' => 'rider_payout', 'max_amount' => 500000, 'sla_hours' => 12]);
\App\Models\ApprovalThresholdConfig::create(['department' => 'rider_ops', 'unit_type' => 'national', 'entity_type' => 'rider_payout', 'max_amount' => null, 'sla_hours' => 8]);
$rider = \App\Models\Rider::create(['name' => 'Test Rider', 'phone' => '9999999999', 'state' => 'Haryana', 'district' => 'Bhiwani']);
$cycle = \App\Models\RiderPayoutCycle::create(['rider_id' => $rider->id, 'cycle_date' => '2026-07-27', 'net_payable' => 12000, 'total_orders' => 40, 'status' => 'generated']);
$instR = \App\Services\RiderPayoutApprovalBridge::originateRiderPayout($cycle, $distOfficer);
$instR->refresh();
check('rider payout originates at rider_ops district', $instR->currentOrgUnit->id === $riderOpsDist->id, $instR->currentOrgUnit->name ?? '?');
check('rider payout approvable within threshold (₹12K ≤ ₹50K)', ApprovalWorkflowEngine::decorate($instR)['approvable']);
ApprovalWorkflowEngine::approve($instR, $distOfficer, 'Rider payout verified');
$instR->refresh();
check('rider payout approved', $instR->status === 'approved');
check('rider finalizer ran — cycle pending_payment', \App\Models\RiderPayoutCycle::find($cycle->id)->status === 'pending_payment');
$bigCycle = \App\Models\RiderPayoutCycle::create(['rider_id' => $rider->id, 'cycle_date' => '2026-08-03', 'net_payable' => 200000, 'total_orders' => 400, 'status' => 'generated']);
$instR2 = \App\Services\RiderPayoutApprovalBridge::originateRiderPayout($bigCycle, $distOfficer);
$instR2->refresh();
check('rider payout NOT approvable above threshold (₹2L > ₹50K)', ! ApprovalWorkflowEngine::decorate($instR2)['approvable']);
ApprovalWorkflowEngine::escalate($instR2, $distOfficer, 'above threshold');
$instR2->refresh();
check('rider payout escalates to state', $instR2->currentOrgUnit->id === $riderOpsState->id, $instR2->currentOrgUnit->name ?? '?');

echo "=== Test 13: Account Management — ads & offers through the engine (§6) ===\n";
$amHq = OrgUnit::create(['department' => 'account_management', 'unit_type' => 'national', 'name' => 'Account Mgmt HQ']);
$amState = OrgUnit::create(['department' => 'account_management', 'unit_type' => 'state', 'name' => 'Account Mgmt Haryana', 'parent_org_unit_id' => $amHq->id, 'geography_scope' => [['state' => 'Haryana']]]);
$amDist = OrgUnit::create(['department' => 'account_management', 'unit_type' => 'district', 'name' => 'Account Mgmt Bhiwani', 'parent_org_unit_id' => $amState->id, 'geography_scope' => [['state' => 'Haryana', 'district' => 'Bhiwani']]]);
OrgRole::create(['org_unit_id' => $amDist->id, 'title' => 'AM District Officer', 'assigned_user_id' => $distOfficer->id]);
OrgRole::create(['org_unit_id' => $amState->id, 'title' => 'AM State Officer', 'assigned_user_id' => $stateOfficer->id]);
\App\Models\ApprovalThresholdConfig::create(['department' => 'account_management', 'unit_type' => 'district', 'entity_type' => 'sponsored_listing', 'max_amount' => 100000, 'sla_hours' => 24]);
\App\Models\ApprovalThresholdConfig::create(['department' => 'account_management', 'unit_type' => 'state', 'entity_type' => 'sponsored_listing', 'max_amount' => 1000000, 'sla_hours' => 12]);
\App\Models\ApprovalThresholdConfig::create(['department' => 'account_management', 'unit_type' => 'national', 'entity_type' => 'sponsored_listing', 'max_amount' => null, 'sla_hours' => 8]);
\App\Models\ApprovalThresholdConfig::create(['department' => 'account_management', 'unit_type' => 'district', 'entity_type' => 'offer', 'max_amount' => 100000, 'sla_hours' => 24]);
\App\Models\ApprovalThresholdConfig::create(['department' => 'account_management', 'unit_type' => 'state', 'entity_type' => 'offer', 'max_amount' => 1000000, 'sla_hours' => 12]);
\App\Models\ApprovalThresholdConfig::create(['department' => 'account_management', 'unit_type' => 'national', 'entity_type' => 'offer', 'max_amount' => null, 'sla_hours' => 8]);
$amRestaurant = \App\Models\Restaurant::create(['name' => 'Test AM Restaurant', 'state' => 'Haryana', 'district' => 'Bhiwani']);
$ad = \App\Models\SponsoredListing::create(['restaurant_id' => $amRestaurant->id, 'ad_type' => 'card', 'daily_budget' => 2000, 'weekly_budget' => 14000, 'approval_status' => 'pending']);
$instAd = \App\Services\AccountManagementApprovalBridge::originateSponsoredListing($ad, $distOfficer);
$instAd->refresh();
check('ad originates at AM district', $instAd->currentOrgUnit->id === $amDist->id, $instAd->currentOrgUnit->name ?? '?');
check('ad approvable within threshold (₹14K ≤ ₹1L)', ApprovalWorkflowEngine::decorate($instAd)['approvable']);
ApprovalWorkflowEngine::approve($instAd, $distOfficer, 'Ad creative verified');
$instAd->refresh();
check('ad approved', $instAd->status === 'approved');
check('ad finalizer ran — published', \App\Models\SponsoredListing::find($ad->id)->approval_status === 'approved');
$bigAd = \App\Models\SponsoredListing::create(['restaurant_id' => $amRestaurant->id, 'ad_type' => 'ppc', 'daily_budget' => 50000, 'weekly_budget' => 350000, 'approval_status' => 'pending']);
$instAd2 = \App\Services\AccountManagementApprovalBridge::originateSponsoredListing($bigAd, $distOfficer);
$instAd2->refresh();
check('ad NOT approvable above threshold (₹3.5L > ₹1L)', ! ApprovalWorkflowEngine::decorate($instAd2)['approvable']);
$offer = \App\Models\Offer::create(['restaurant_id' => $amRestaurant->id, 'type' => 'percentage', 'title' => 'Test Offer', 'discount_value' => 20, 'max_discount' => 100, 'budget' => 50000, 'approval_status' => 'pending']);
$instOffer = \App\Services\AccountManagementApprovalBridge::originateOffer($offer, $distOfficer);
$instOffer->refresh();
check('offer originates at AM district', $instOffer->currentOrgUnit->id === $amDist->id, $instOffer->currentOrgUnit->name ?? '?');
ApprovalWorkflowEngine::approve($instOffer, $distOfficer, 'Offer terms verified');
$instOffer->refresh();
check('offer finalizer ran — published', \App\Models\Offer::find($offer->id)->approval_status === 'approved');
check('AM entity summary shows restaurant', ApprovalWorkflowEngine::decorate($instAd)['entity_summary']['restaurant'] === 'Test AM Restaurant');

echo "=== Test 14: Support — complaint refund through the engine (§6) ===\n";
$supHq = OrgUnit::create(['department' => 'support', 'unit_type' => 'national', 'name' => 'Support HQ']);
$supState = OrgUnit::create(['department' => 'support', 'unit_type' => 'state', 'name' => 'Support Haryana', 'parent_org_unit_id' => $supHq->id, 'geography_scope' => [['state' => 'Haryana']]]);
$supDist = OrgUnit::create(['department' => 'support', 'unit_type' => 'district', 'name' => 'Support Bhiwani', 'parent_org_unit_id' => $supState->id, 'geography_scope' => [['state' => 'Haryana', 'district' => 'Bhiwani']]]);
OrgRole::create(['org_unit_id' => $supDist->id, 'title' => 'Support District Officer', 'assigned_user_id' => $distOfficer->id]);
OrgRole::create(['org_unit_id' => $supState->id, 'title' => 'Support State Officer', 'assigned_user_id' => $stateOfficer->id]);
\App\Models\ApprovalThresholdConfig::create(['department' => 'support', 'unit_type' => 'district', 'entity_type' => 'complaint_refund', 'max_amount' => 10000, 'sla_hours' => 24]);
\App\Models\ApprovalThresholdConfig::create(['department' => 'support', 'unit_type' => 'state', 'entity_type' => 'complaint_refund', 'max_amount' => 100000, 'sla_hours' => 12]);
\App\Models\ApprovalThresholdConfig::create(['department' => 'support', 'unit_type' => 'national', 'entity_type' => 'complaint_refund', 'max_amount' => null, 'sla_hours' => 8]);
$supUser = User::create(['name' => 'Sup User', 'email' => 'sup@test.com', 'password' => 'x']);
$supOrder = \App\Models\Order::create(['user_id' => $supUser->id, 'restaurant_id' => $amRestaurant->id, 'status' => 'delivered', 'grand_total' => 20000, 'delivery_fee_restaurant' => 20000]);
$complaint = \App\Models\Complaint::create(['user_id' => $supUser->id, 'order_id' => $supOrder->id, 'restaurant_id' => $amRestaurant->id, 'reason' => 'cold food', 'status' => 'responded', 'refund_amount' => 5000, 'refund_processed' => false]);
$complaint = \App\Models\Complaint::where('order_id', $supOrder->id)->where('refund_amount', 5000)->firstOrFail();
$instSup = \App\Services\SupportApprovalBridge::originateComplaintRefund($complaint, $distOfficer);
$instSup->refresh();
check('complaint refund originates at support district', $instSup->currentOrgUnit->id === $supDist->id, $instSup->currentOrgUnit->name ?? '?');
check('complaint refund approvable within threshold (₹5K ≤ ₹10K)', ApprovalWorkflowEngine::decorate($instSup)['approvable']);
check('complaint refund summary shows order', ApprovalWorkflowEngine::decorate($instSup)['entity_summary']['order_id'] === $supOrder->id);
ApprovalWorkflowEngine::approve($instSup, $distOfficer, 'Refund verified');
$instSup->refresh();
check('complaint refund approved', $instSup->status === 'approved');
$refreshed = \App\Models\Complaint::find($complaint->id);
check('support finalizer ran — refund processed', $refreshed->refund_processed === true);
check('support finalizer ran — complaint resolved', $refreshed->status === 'resolved');
check('wallet credited', (float) \App\Models\User::find($supUser->id)->wallet_balance === 5000.0);
check('ledger entry written', \App\Models\WalletTransaction::where('user_id', $supUser->id)->where('type', 'credit')->count() === 1);
$bigComplaint = \App\Models\Complaint::create(['user_id' => $supUser->id, 'order_id' => $supOrder->id, 'restaurant_id' => $amRestaurant->id, 'reason' => 'major issue', 'status' => 'responded', 'refund_amount' => 50000, 'refund_processed' => false]);
$bigComplaint = \App\Models\Complaint::where('order_id', $supOrder->id)->where('refund_amount', 50000)->firstOrFail();
$instSup2 = \App\Services\SupportApprovalBridge::originateComplaintRefund($bigComplaint, $distOfficer);
$instSup2->refresh();
check('complaint refund NOT approvable above threshold (₹50K > ₹10K)', ! ApprovalWorkflowEngine::decorate($instSup2)['approvable']);
ApprovalWorkflowEngine::escalate($instSup2, $distOfficer, 'above threshold');
$instSup2->refresh();
check('complaint refund escalates to state', $instSup2->currentOrgUnit->id === $supState->id, $instSup2->currentOrgUnit->name ?? '?');

echo "=== Test 15: Finance — invoices & recoveries through the engine (§6) ===\n";
$inv = \App\Models\Invoice::create(['restaurant_id' => $amRestaurant->id, 'type' => 'commission', 'invoice_number' => 'INV-T15-1', 'amount' => 8000, 'total_amount' => 9440, 'status' => 'pending']);
$instInv = \App\Services\FinanceApprovalBridge::originateInvoice($inv, $distOfficer);
$instInv->refresh();
check('invoice originates at finance region (deepest)', $instInv->currentOrgUnit->id === $bhiwaniRegion->id, $instInv->currentOrgUnit->name ?? '?');
check('invoice approvable within threshold (₹9440 ≤ ₹25K)', ApprovalWorkflowEngine::decorate($instInv)['approvable']);
check('invoice summary shows number', ApprovalWorkflowEngine::decorate($instInv)['entity_summary']['invoice_number'] === 'INV-T15-1');
ApprovalWorkflowEngine::approve($instInv, $regOfficer, 'Invoice verified');
$instInv->refresh();
check('invoice approved', $instInv->status === 'approved');
check('invoice finalizer ran — status approved', \App\Models\Invoice::find($inv->id)->status === 'approved');
$rec = \App\Models\Recovery::create(['restaurant_id' => $amRestaurant->id, 'reference_type' => 'inventory', 'amount' => 15000, 'status' => 'pending']);
$instRec = \App\Services\FinanceApprovalBridge::originateRecovery($rec, $distOfficer);
$instRec->refresh();
check('recovery originates at finance region (deepest)', $instRec->currentOrgUnit->id === $bhiwaniRegion->id, $instRec->currentOrgUnit->name ?? '?');
check('recovery approvable within threshold (₹15K ≤ ₹25K)', ApprovalWorkflowEngine::decorate($instRec)['approvable']);
ApprovalWorkflowEngine::approve($instRec, $regOfficer, 'Recovery verified');
$instRec->refresh();
check('recovery finalizer ran — invoiced', \App\Models\Recovery::find($rec->id)->status === 'invoiced');

echo "=== Test 16: Finance — GST filing through the engine (§6) ===\n";
\App\Models\ApprovalThresholdConfig::create(['department' => 'finance', 'unit_type' => 'state', 'entity_type' => 'gst_filing', 'max_amount' => 2500000, 'sla_hours' => 12]);
\App\Models\ApprovalThresholdConfig::create(['department' => 'finance', 'unit_type' => 'national', 'entity_type' => 'gst_filing', 'max_amount' => null, 'sla_hours' => 8]);
$filing = \App\Models\GstLiabilityRegister::create(['filing_period' => '2026-07', 'state' => 'Haryana', 'total_gst_collected' => 120000, 'total_gst_deposited' => 0, 'status' => 'pending']);
$instGst = \App\Services\FinanceApprovalBridge::originateGstFiling($filing, $distOfficer);
$instGst->refresh();
check('gst filing originates at finance state (state-level geo)', $instGst->currentOrgUnit->id === $haryana->id, $instGst->currentOrgUnit->name ?? '?');
check('gst filing approvable within state threshold (₹1.2L ≤ ₹25L)', ApprovalWorkflowEngine::decorate($instGst)['approvable']);
check('gst filing summary shows period', ApprovalWorkflowEngine::decorate($instGst)['entity_summary']['filing_period'] === '2026-07');
ApprovalWorkflowEngine::approve($instGst, $stateOfficer, 'GST liability verified');
$instGst->refresh();
check('gst filing approved', $instGst->status === 'approved');
$filingRef = \App\Models\GstLiabilityRegister::find($filing->id);
check('gst finalizer ran — filed', $filingRef->status === 'filed');
check('gst finalizer ran — deposited amount recorded', (float) $filingRef->total_gst_deposited === 120000.0);
$bigFiling = \App\Models\GstLiabilityRegister::create(['filing_period' => '2026-08', 'state' => 'Haryana', 'total_gst_collected' => 3000000, 'total_gst_deposited' => 0, 'status' => 'pending']);
$instGst2 = \App\Services\FinanceApprovalBridge::originateGstFiling($bigFiling, $distOfficer);
$instGst2->refresh();
check('gst filing NOT approvable above state threshold (₹30L > ₹25L)', ! ApprovalWorkflowEngine::decorate($instGst2)['approvable']);
ApprovalWorkflowEngine::escalate($instGst2, $stateOfficer, 'above threshold');
$instGst2->refresh();
check('gst filing escalates to CAO', $instGst2->currentOrgUnit->id === $national->id, $instGst2->currentOrgUnit->name ?? '?');

echo "=== Test 17: Rider Ops — KYC verification through the engine (§6) ===\n";
$kycRider = \App\Models\Rider::create(['name' => 'KYC Rider', 'phone' => '8888888888', 'state' => 'Haryana', 'district' => 'Bhiwani', 'is_verified' => false]);
\App\Models\RiderDocument::create(['rider_id' => $kycRider->id, 'document_type' => 'aadhaar_front', 'status' => 'pending']);
\App\Models\RiderDocument::create(['rider_id' => $kycRider->id, 'document_type' => 'aadhaar_back', 'status' => 'pending']);
\App\Models\RiderDocument::create(['rider_id' => $kycRider->id, 'document_type' => 'driving_license_front', 'status' => 'pending']);
\App\Models\RiderDocument::create(['rider_id' => $kycRider->id, 'document_type' => 'pan_card', 'status' => 'pending']);
\App\Models\RiderDocument::create(['rider_id' => $kycRider->id, 'document_type' => 'selfie', 'status' => 'pending']);
$instKyc = \App\Services\RiderKycApprovalBridge::originateKycVerification($kycRider, $distOfficer);
$instKyc->refresh();
check('kyc verification originates at rider_ops district', $instKyc->currentOrgUnit->id === $riderOpsDist->id, $instKyc->currentOrgUnit->name ?? '?');
check('kyc summary shows rider + docs', ApprovalWorkflowEngine::decorate($instKyc)['entity_summary']['rider'] === 'KYC Rider');
check('kyc summary shows 5 docs uploaded', ApprovalWorkflowEngine::decorate($instKyc)['entity_summary']['documents_uploaded'] === 5);
ApprovalWorkflowEngine::approve($instKyc, $distOfficer, 'All documents verified');
$instKyc->refresh();
check('kyc approved', $instKyc->status === 'approved');
$kycRiderRef = \App\Models\Rider::find($kycRider->id);
check('kyc finalizer ran — rider verified', (bool) $kycRiderRef->is_verified === true);
check('kyc finalizer ran — verification_status', $kycRiderRef->verification_status === 'verified');
check('kyc finalizer ran — docs approved', \App\Models\RiderDocument::where('rider_id', $kycRider->id)->where('status', 'approved')->count() === 5);
check('kyc finalizer ran — verified_by set', \App\Models\RiderDocument::where('rider_id', $kycRider->id)->whereNotNull('verified_by')->count() === 5);

echo "=== Test 18: Account Management — geography-routed restaurant→AM assignment (§6) ===\n";
$amUser = User::create(['name' => 'AM Officer', 'email' => 'amo@test.com', 'password' => 'x', 'is_admin' => true, 'admin_role' => 'account_manager']);
$instAssign = \App\Services\AccountManagementApprovalBridge::originateRestaurantAssignment($amRestaurant, $amUser, $distOfficer);
$instAssign->refresh();
check('am assignment originates at AM district', $instAssign->currentOrgUnit->id === $amDist->id, $instAssign->currentOrgUnit->name ?? '?');
check('am assignment approvable (amount 0)', ApprovalWorkflowEngine::decorate($instAssign)['approvable']);
check('am assignment summary shows restaurant', ApprovalWorkflowEngine::decorate($instAssign)['entity_summary']['restaurant'] === 'Test AM Restaurant');
ApprovalWorkflowEngine::approve($instAssign, $distOfficer, 'Assign this restaurant to the AM');
$instAssign->refresh();
check('am assignment approved', $instAssign->status === 'approved');
$assignment = \App\Models\AccountManagerAssignment::where('restaurant_id', $amRestaurant->id)->where('is_active', true)->first();
check('am assignment finalizer ran — active assignment created', $assignment !== null);
check('am assignment finalizer ran — assigned to AM', $assignment?->account_manager_id === $amUser->id);
check('am assignment finalizer ran — assigned_by approver', $assignment?->assigned_by === $distOfficer->id);

echo "=== Test 19: threshold changes never retroactively alter in-flight items (§10) ===\n";
// Region threshold for vendor_payout is currently ₹25,000 (line 39).
$pay19 = VendorPayment::create(['restaurant_id' => $rest->id, 'net_payable' => 15000, 'status' => 'pending', 'payment_period' => 'W19']);
$inst19 = FinanceApprovalBridge::originateVendorPayout($pay19, $distOfficer);
$inst19->refresh();
check('in-flight item origina at region (deepest)', $inst19->currentOrgUnit->id === $bhiwaniRegion->id, $inst19->currentOrgUnit->name ?? '?');
check('in-flight item approvable at ₹15K ≤ ₹25K before config change', ApprovalWorkflowEngine::decorate($inst19)['approvable']);
check('in-flight item pinned max ₹25K at origin', ApprovalWorkflowEngine::decorate($inst19)['pinned_max_amount'] == 25000, ApprovalWorkflowEngine::decorate($inst19)['pinned_max_amount'] ?? 'null');

// Tighten the region threshold to ₹10K while the item is in flight.
\App\Models\ApprovalThresholdConfig::where('department', 'finance')->where('unit_type', 'region')->where('entity_type', 'vendor_payout')->update(['max_amount' => 10000, 'updated_at' => now()]);

check('config change did NOT retroactively alter in-flight item — still approvable at ₹15K', ApprovalWorkflowEngine::decorate($inst19)['approvable']);
check('in-flight item still pinned ₹25K after config change', ApprovalWorkflowEngine::decorate($inst19)['pinned_max_amount'] == 25000);
ApprovalWorkflowEngine::approve($inst19, $regOfficer, 'Approved under pinned threshold');
$inst19->refresh();
check('in-flight item still approved under pinned ₹25K', $inst19->status === 'approved');

// A NEW item must honor the new ₹10K threshold.
$pay19b = VendorPayment::create(['restaurant_id' => $rest->id, 'net_payable' => 15000, 'status' => 'pending', 'payment_period' => 'W19B']);
$inst19b = FinanceApprovalBridge::originateVendorPayout($pay19b, $distOfficer);
$inst19b->refresh();
check('new item NOT approvable at ₹15K > new ₹10K', ! ApprovalWorkflowEngine::decorate($inst19b)['approvable']);
check('new item pinned ₹10K', ApprovalWorkflowEngine::decorate($inst19b)['pinned_max_amount'] == 10000);
$refused19 = false;
try { ApprovalWorkflowEngine::approve($inst19b, $regOfficer, 'try'); } catch (\Exception $e) { $refused19 = true; }
check('new item refused at region under ₹10K', $refused19);

// Restore region threshold for other suites' assumptions (Test 15 uses ₹15K recoveries/invoices).
\App\Models\ApprovalThresholdConfig::where('department', 'finance')->where('unit_type', 'region')->where('entity_type', 'vendor_payout')->update(['max_amount' => 25000, 'updated_at' => now()]);

echo "\n" . str_repeat('=', 40) . "\n";
echo "RESULT: {$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);