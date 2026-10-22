<?php

namespace App\Services;

use App\Models\ApprovalWorkflowInstance;
use App\Models\FoodWasteClaim;
use App\Models\GstLiabilityRegister;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Recovery;
use App\Models\RestaurantPayoutBalance;
use App\Models\User;
use App\Models\VendorPayment;
use Carbon\Carbon;

/**
 * Finance/Accounts reference implementation (§6).
 *
 * Vendor payout confirmations (weekly restaurant payouts), invoice
 * corrections/approvals, and recovery approvals travel through the generic
 * engine. Final approval triggers the actual downstream action.
 */
class FinanceApprovalBridge
{
    /**
     * Originate a vendor payout for approval at the district covering its
     * restaurant's geography (§5.2 step 1).
     */
    public static function originateVendorPayout(VendorPayment $payment, User $actor, bool $isException = false): ApprovalWorkflowInstance
    {
        $restaurant = $payment->restaurant;

        return ApprovalWorkflowEngine::originate(
            department: 'finance',
            entityType: 'vendor_payout',
            entityId: $payment->id,
            amount: (float) $payment->net_payable,
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
     * Originate an invoice for approval/correction (commission/ads/recovery
     * invoices awaiting sign-off). Route amount = total payable.
     */
    public static function originateInvoice(Invoice $invoice, User $actor, bool $isException = false): ApprovalWorkflowInstance
    {
        $restaurant = $invoice->restaurant;

        return ApprovalWorkflowEngine::originate(
            department: 'finance',
            entityType: 'invoice',
            entityId: $invoice->id,
            amount: (float) ($invoice->total_amount ?: $invoice->amount),
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
     * Originate a recovery approval (restaurant recovery raised against an
     * outlet — e.g. damaged inventory chargebacks).
     */
    public static function originateRecovery(Recovery $recovery, User $actor, bool $isException = false): ApprovalWorkflowInstance
    {
        $restaurant = $recovery->restaurant;

        return ApprovalWorkflowEngine::originate(
            department: 'finance',
            entityType: 'recovery',
            entityId: $recovery->id,
            amount: (float) $recovery->amount,
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
     * Originate a food-waste claim for an order the platform cancelled after
     * the kitchen had prepared food. Needs proof and an amount cap; moves only
     * through the approval engine.
     */
    public static function originateFoodWasteClaim(FoodWasteClaim $claim, User $actor, bool $isException = false): ApprovalWorkflowInstance
    {
        $restaurant = $claim->restaurant;

        $instance = ApprovalWorkflowEngine::originate(
            department: 'finance',
            entityType: 'food_waste_claim',
            entityId: $claim->id,
            amount: (float) $claim->amount,
            geography: [
                'state' => $restaurant?->state,
                'district' => $restaurant?->district,
            ],
            isException: $isException || $restaurant?->state === null,
            originOrgUnitId: null,
            actor: $actor
        );

        $claim->update([
            'status' => FoodWasteClaim::STATUS_PENDING_REVIEW,
            'approval_instance_id' => $instance->id,
        ]);

        return $instance;
    }

    /**
     * Final approval: credit the restaurant's payout balance once.
     */
    public static function onFoodWasteClaimApproved(ApprovalWorkflowInstance $instance): void
    {
        $claim = FoodWasteClaim::find($instance->entity_id);
        if (! $claim || $claim->status === FoodWasteClaim::STATUS_APPROVED) {
            return;
        }

        $claim->update([
            'status' => FoodWasteClaim::STATUS_APPROVED,
            'approved_at' => Carbon::now(),
        ]);

        $balance = RestaurantPayoutBalance::firstOrCreate(
            ['restaurant_id' => $claim->restaurant_id],
            ['running_balance' => 0, 'total_paid_to_date' => 0, 'total_earnings_to_date' => 0, 'last_updated_at' => Carbon::now()],
        );

        $balance->increment('running_balance', (float) $claim->amount);
        $balance->increment('total_earnings_to_date', (float) $claim->amount);
        $balance->update(['last_updated_at' => Carbon::now()]);
    }

    public static function summaryFoodWasteClaim(string $entityId): array
    {
        $claim = FoodWasteClaim::with('restaurant')->find($entityId);
        if (! $claim) {
            return ['type' => 'food_waste_claim', 'id' => $entityId, 'label' => 'Food waste claim #' . substr($entityId, 0, 8)];
        }

        return [
            'type' => 'food_waste_claim',
            'id' => $claim->id,
            'label' => 'Food waste — ' . ($claim->restaurant?->name ?? 'Restaurant'),
            'restaurant' => $claim->restaurant?->name,
            'restaurant_state' => $claim->restaurant?->state,
            'restaurant_district' => $claim->restaurant?->district,
            'amount' => (float) $claim->amount,
            'reason' => $claim->reason,
            'route_amount' => (float) $claim->amount,
        ];
    }

    /**
     * Downstream action on final approval: mark the payout approved.
     */
    public static function onVendorPayoutApproved(ApprovalWorkflowInstance $instance): void
    {
        $payment = VendorPayment::find($instance->entity_id);
        if (! $payment) {
            return;
        }

        $payment->status = 'approved';
        $payment->approved_by = $instance->steps()
            ->where('action', ApprovalWorkflowEngine::ACTION_APPROVED)
            ->orderByDesc('created_at')->first()?->actor_user_id;
        $payment->approved_at = Carbon::now();
        $payment->save();
    }

    /**
     * Downstream action on final approval: approve the invoice.
     */
    public static function onInvoiceApproved(ApprovalWorkflowInstance $instance): void
    {
        $invoice = Invoice::find($instance->entity_id);
        if (! $invoice) {
            return;
        }

        $invoice->status = 'approved';
        $invoice->approved_by = $instance->steps()
            ->where('action', ApprovalWorkflowEngine::ACTION_APPROVED)
            ->orderByDesc('created_at')->first()?->actor_user_id;
        $invoice->approved_at = Carbon::now();
        $invoice->save();
    }

    /**
     * Downstream action on final approval: mark the recovery approved for
     * invoicing (pending → invoiced).
     */
    public static function onRecoveryApproved(ApprovalWorkflowInstance $instance): void
    {
        $recovery = Recovery::find($instance->entity_id);
        if (! $recovery || $recovery->status === 'waived') {
            return;
        }

        $recovery->status = 'invoiced';
        $recovery->save();
    }

    /**
     * Originate a GST filing (monthly GSTR-1/3B liability deposit) for the
     * given state. Route amount = GST collected for the period.
     */
    public static function originateGstFiling(GstLiabilityRegister $filing, User $actor, bool $isException = false): ApprovalWorkflowInstance
    {
        return ApprovalWorkflowEngine::originate(
            department: 'finance',
            entityType: 'gst_filing',
            entityId: $filing->id,
            amount: (float) $filing->total_gst_collected,
            geography: [
                'state' => $filing->state,
                'district' => null,
            ],
            isException: $isException || $filing->state === null,
            originOrgUnitId: null,
            actor: $actor
        );
    }

    /**
     * Downstream action on final approval: mark the filing deposited.
     */
    public static function onGstFilingApproved(ApprovalWorkflowInstance $instance): void
    {
        $filing = GstLiabilityRegister::find($instance->entity_id);
        if (! $filing) {
            return;
        }

        $filing->status = 'filed';
        $filing->total_gst_deposited = (float) $filing->total_gst_collected;
        $filing->deposited_at = Carbon::now();
        $filing->save();
    }

    public static function summary(string $entityType, string $entityId): array
    {
        if ($entityType === 'gst_filing') {
            $filing = GstLiabilityRegister::find($entityId);
            if (! $filing) {
                return ['type' => 'gst_filing', 'id' => $entityId, 'label' => 'GST Filing #' . substr($entityId, 0, 8)];
            }

            return [
                'type' => 'gst_filing',
                'id' => $filing->id,
                'label' => 'GST ' . ($filing->state ? $filing->state . ' ' : '') . $filing->filing_period,
                'state' => $filing->state,
                'filing_period' => $filing->filing_period,
                'total_gst_collected' => (float) $filing->total_gst_collected,
                'route_amount' => (float) $filing->total_gst_collected,
                'filing_status' => $filing->status,
            ];
        }

        if ($entityType === 'invoice') {
            $invoice = Invoice::with('restaurant')->find($entityId);
            if (! $invoice) {
                return ['type' => 'invoice', 'id' => $entityId, 'label' => 'Invoice #' . substr($entityId, 0, 8)];
            }

            return [
                'type' => 'invoice',
                'id' => $invoice->id,
                'label' => $invoice->invoice_number ? ('Invoice ' . $invoice->invoice_number) : ('Invoice — ' . ($invoice->restaurant?->name ?? 'Restaurant')),
                'restaurant' => $invoice->restaurant?->name,
                'restaurant_state' => $invoice->restaurant?->state,
                'restaurant_district' => $invoice->restaurant?->district,
                'invoice_number' => $invoice->invoice_number,
                'type' => 'invoice',
                'period' => ($invoice->period_start?->toDateString() ?? '') . ' → ' . ($invoice->period_end?->toDateString() ?? ''),
                'total_amount' => (float) ($invoice->total_amount ?: $invoice->amount),
                'route_amount' => (float) ($invoice->total_amount ?: $invoice->amount),
                'invoice_status' => $invoice->status,
            ];
        }

        if ($entityType === 'recovery') {
            $recovery = Recovery::with('restaurant')->find($entityId);
            if (! $recovery) {
                return ['type' => 'recovery', 'id' => $entityId, 'label' => 'Recovery #' . substr($entityId, 0, 8)];
            }

            return [
                'type' => 'recovery',
                'id' => $recovery->id,
                'label' => 'Recovery ₹' . (float) $recovery->amount . ' — ' . ($recovery->restaurant?->name ?? 'Restaurant'),
                'restaurant' => $recovery->restaurant?->name,
                'restaurant_state' => $recovery->restaurant?->state,
                'restaurant_district' => $recovery->restaurant?->district,
                'reference' => $recovery->reference_type,
                'amount' => (float) $recovery->amount,
                'route_amount' => (float) $recovery->amount,
                'recovery_status' => $recovery->status,
            ];
        }

        $payment = VendorPayment::with('restaurant')->find($entityId);
        if (! $payment) {
            return ['type' => 'vendor_payout', 'id' => $entityId, 'label' => 'Payout #' . substr($entityId, 0, 8)];
        }

        return [
            'type' => 'vendor_payout',
            'id' => $payment->id,
            'label' => 'Weekly payout — ' . ($payment->restaurant?->name ?? 'Restaurant'),
            'restaurant' => $payment->restaurant?->name,
            'restaurant_state' => $payment->restaurant?->state,
            'restaurant_district' => $payment->restaurant?->district,
            'week' => $payment->payment_period,
            'net_payable' => (float) $payment->net_payable,
            'utr' => $payment->utr_number,
        ];
    }
}

