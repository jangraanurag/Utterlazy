<?php

namespace App\Services;

use App\Models\AccountManagerAssignment;
use App\Models\ApprovalWorkflowInstance;
use App\Models\Offer;
use App\Models\OrgRole;
use App\Models\OrgUnit;
use App\Models\Restaurant;
use App\Models\SponsoredListing;
use App\Models\User;
use App\Models\WaPlacement;
use App\Models\WaCityFloor;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Account Management reference implementation (§6).
 *
 * AM-created ads (sponsored listings) and offers travel through the generic
 * engine instead of the restaurant-side OTP flow. Final approval marks the
 * item approved for launch.
 */
class AccountManagementApprovalBridge
{
    /**
     * Originate a sponsored listing (ad) for approval.
     * Route amount = weekly budget commitment (falls back to daily budget).
     */
    public static function originateSponsoredListing(SponsoredListing $ad, User $actor, bool $isException = false): ApprovalWorkflowInstance
    {
        $restaurant = $ad->restaurant;
        $amount = (float) ($ad->weekly_budget ?: $ad->daily_budget ?: $ad->bid_amount);

        return ApprovalWorkflowEngine::originate(
            department: 'account_management',
            entityType: 'sponsored_listing',
            entityId: $ad->id,
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
     * Originate an offer for approval.
     * Route amount = budget (falls back to max discount, then discount value).
     */
    public static function originateOffer(Offer $offer, User $actor, bool $isException = false): ApprovalWorkflowInstance
    {
        $restaurant = $offer->restaurant;
        $amount = (float) ($offer->budget ?: $offer->max_discount ?: $offer->discount_value);

        return ApprovalWorkflowEngine::originate(
            department: 'account_management',
            entityType: 'offer',
            entityId: $offer->id,
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
     * Downstream action on final approval: publish the ad.
     */
    public static function onSponsoredListingApproved(ApprovalWorkflowInstance $instance): void
    {
        $ad = SponsoredListing::find($instance->entity_id);
        if (! $ad) {
            return;
        }

        $startDate = $ad->start_date ? Carbon::parse($ad->start_date) : null;
        $ad->update([
            'approval_status' => 'approved',
            'approved_at' => Carbon::now(),
            'approved_via' => 'org_engine',
            'status' => $startDate && ($startDate->isToday() || $startDate->isPast()) ? 'active' : 'scheduled',
        ]);
    }

    /**
     * Downstream action on final approval: publish the offer.
     */
    public static function onOfferApproved(ApprovalWorkflowInstance $instance): void
    {
        $offer = Offer::find($instance->entity_id);
        if (! $offer) {
            return;
        }

        $offer->update([
            'approval_status' => 'approved',
            'approved_at' => Carbon::now(),
            'approved_via' => 'org_engine',
            'status' => 'active',
        ]);
    }

    /**
     * Originate a WhatsApp paid placement for approval.
     * Route amount = daily budget commitment.
     */
    public static function originateWaPlacement(WaPlacement $placement, User $actor, bool $isException = false): ApprovalWorkflowInstance
    {
        $restaurant = $placement->restaurant;

        $instance = ApprovalWorkflowEngine::originate(
            department: 'account_management',
            entityType: 'wa_placement',
            entityId: $placement->id,
            amount: (float) $placement->daily_budget,
            geography: [
                'state' => $restaurant?->state,
                'district' => $restaurant?->district,
            ],
            isException: $isException || $restaurant?->state === null,
            originOrgUnitId: null,
            actor: $actor
        );

        $placement->update([
            'status' => WaPlacement::STATUS_PENDING_REVIEW,
            'approval_instance_id' => $instance->id,
        ]);

        return $instance;
    }

    /**
     * Downstream action on final approval: activate placement and pin the
     * floor at approval time. Floor changes never retroactively apply (§10).
     */
    public static function onWaPlacementApproved(ApprovalWorkflowInstance $instance): void
    {
        $placement = WaPlacement::find($instance->entity_id);
        if (! $placement) {
            return;
        }

        $floor = WaCityFloor::find($placement->city_id)?->floor_cpc ?? 0.0;
        $now = Carbon::now();

        $placement->update([
            'status' => WaPlacement::STATUS_ACTIVE,
            'pinned_floor' => $floor,
            'start_at' => $placement->start_at && $placement->start_at->isPast() ? $now : $placement->start_at,
        ]);

        app(OutboxPublisher::class)->enqueue(
            'wa_placement',
            $placement->id,
            'wa_placement.approved',
            [
                'placement_id' => $placement->id,
                'restaurant_id' => $placement->restaurant_id,
                'city_id' => $placement->city_id,
                'status' => $placement->status,
                'daily_budget' => (float) $placement->daily_budget,
                'cpc_bid' => (float) $placement->cpc_bid,
                'pinned_floor' => (float) $placement->pinned_floor,
            ],
            $placement->city_id,
        );
    }

    /**
     * Originate a restaurant→AM assignment for approval.
     * The assignment is geography-routed: it originates at the AM org unit
     * covering the restaurant's state/district (§6 AM row), not a flat
     * admin-assigns-anyone model.
     */
    public static function originateRestaurantAssignment(Restaurant $restaurant, User $am, User $actor, bool $isException = false): ApprovalWorkflowInstance
    {
        if (! $am->is_admin || ($am->admin_role ?? '') !== 'account_manager') {
            throw ValidationException::withMessages([
                'account_manager_id' => 'The selected user is not an account manager.',
            ]);
        }

        $instance = ApprovalWorkflowEngine::originate(
            department: 'account_management',
            entityType: 'restaurant_assignment',
            entityId: $restaurant->id,
            amount: 0,
            geography: [
                'state' => $restaurant->state,
                'district' => $restaurant->district,
            ],
            isException: $isException || $restaurant->state === null,
            originOrgUnitId: null,
            actor: $actor
        );

        // Stamp the AM being assigned onto the originated step so the
        // finalizer knows who to assign.
        $instance->steps()->where('action', ApprovalWorkflowEngine::ACTION_ORIGINATED)
            ->latest()
            ->first()
            ?->update(['metadata' => ['account_manager_id' => $am->id]]);

        return $instance;
    }

    /**
     * Downstream action on final approval: create the active assignment.
     */
    public static function onRestaurantAssignmentApproved(ApprovalWorkflowInstance $instance): void
    {
        $restaurant = Restaurant::find($instance->entity_id);
        if (! $restaurant) {
            return;
        }

        $originStep = $instance->steps()->where('action', ApprovalWorkflowEngine::ACTION_ORIGINATED)->first();
        $amId = $originStep?->metadata['account_manager_id'] ?? null;
        $am = $amId ? User::find($amId) : null;
        if (! $am || ! $am->is_admin || ($am->admin_role ?? '') !== 'account_manager') {
            return;
        }

        $approver = $instance->steps()
            ->where('action', ApprovalWorkflowEngine::ACTION_APPROVED)
            ->orderByDesc('created_at')->first()?->actor_user_id;

        AccountManagerAssignment::where('restaurant_id', $restaurant->id)
            ->where('is_active', true)
            ->update(['is_active' => false, 'unassigned_at' => Carbon::now()]);

        AccountManagerAssignment::create([
            'account_manager_id' => $am->id,
            'restaurant_id' => $restaurant->id,
            'assigned_by' => $approver,
            'assigned_at' => Carbon::now(),
            'is_active' => true,
        ]);
    }

    public static function summary(string $entityType, string $entityId): array
    {
        if ($entityType === 'wa_placement') {
            $placement = WaPlacement::with('restaurant')->find($entityId);
            if (! $placement) {
                return ['type' => 'wa_placement', 'id' => $entityId, 'label' => 'WA Placement #' . substr($entityId, 0, 8)];
            }

            return [
                'type' => 'wa_placement',
                'id' => $placement->id,
                'label' => 'WhatsApp Promo — ' . ($placement->restaurant?->name ?? 'Restaurant'),
                'restaurant' => $placement->restaurant?->name,
                'restaurant_state' => $placement->restaurant?->state,
                'restaurant_district' => $placement->restaurant?->district,
                'daily_budget' => (float) $placement->daily_budget,
                'cpc_bid' => (float) $placement->cpc_bid,
                'route_amount' => (float) $placement->daily_budget,
            ];
        }

        if ($entityType === 'restaurant_assignment') {
            $restaurant = Restaurant::find($entityId);
            if (! $restaurant) {
                return ['type' => 'restaurant_assignment', 'id' => $entityId, 'label' => 'AM Assignment #' . substr($entityId, 0, 8)];
            }

            return [
                'type' => 'restaurant_assignment',
                'id' => $restaurant->id,
                'label' => 'AM assignment — ' . $restaurant->name,
                'restaurant' => $restaurant->name,
                'restaurant_state' => $restaurant->state,
                'restaurant_district' => $restaurant->district,
                'route_amount' => 0,
            ];
        }

        if ($entityType === 'sponsored_listing') {
            $ad = SponsoredListing::with('restaurant')->find($entityId);
            if (! $ad) {
                return ['type' => 'sponsored_listing', 'id' => $entityId, 'label' => 'Ad #' . substr($entityId, 0, 8)];
            }
            $amount = (float) ($ad->weekly_budget ?: $ad->daily_budget ?: $ad->bid_amount);

            return [
                'type' => 'sponsored_listing',
                'id' => $ad->id,
                'label' => ($ad->ad_type === 'card' ? 'Card Ad' : 'PPC Ad') . ' — ' . ($ad->restaurant?->name ?? 'Restaurant'),
                'restaurant' => $ad->restaurant?->name,
                'restaurant_state' => $ad->restaurant?->state,
                'restaurant_district' => $ad->restaurant?->district,
                'ad_type' => $ad->ad_type,
                'daily_budget' => (float) $ad->daily_budget,
                'weekly_budget' => (float) $ad->weekly_budget,
                'route_amount' => $amount,
            ];
        }

        $offer = Offer::with('restaurant')->find($entityId);
        if (! $offer) {
            return ['type' => 'offer', 'id' => $entityId, 'label' => 'Offer #' . substr($entityId, 0, 8)];
        }
        $amount = (float) ($offer->budget ?: $offer->max_discount ?: $offer->discount_value);

        return [
            'type' => 'offer',
            'id' => $offer->id,
            'label' => $offer->title . ' — ' . ($offer->restaurant?->name ?? 'Restaurant'),
            'restaurant' => $offer->restaurant?->name,
            'restaurant_state' => $offer->restaurant?->state,
            'restaurant_district' => $offer->restaurant?->district,
            'offer_type' => $offer->type,
            'discount_value' => (float) $offer->discount_value,
            'max_discount' => (float) $offer->max_discount,
            'budget' => (float) $offer->budget,
            'route_amount' => $amount,
        ];
    }
}
