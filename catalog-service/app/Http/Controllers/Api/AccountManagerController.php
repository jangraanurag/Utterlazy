<?php

namespace App\Http\Controllers\Api;

use App\Events\RequiresRestaurantApproval;
use App\Http\Controllers\Controller;
use App\Mail\ApprovalOtpMail;
use App\Models\AccountManagerActivityLog;
use App\Models\AccountManagerAssignment;
use App\Models\Offer;
use App\Models\Restaurant;
use App\Models\RestaurantApprovalOtp;
use App\Models\SponsoredListing;
use App\Models\User;
use App\Services\FcmService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class AccountManagerController extends Controller
{
    // ═══════════════════════════════════════
    //  ADMIN: AM Management
    // ═══════════════════════════════════════

    public function adminIndex()
    {
        $ams = User::where('is_admin', true)
            ->where('admin_role', 'account_manager')
            ->withCount(['assignments' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get();

        return response()->json(['account_managers' => $ams]);
    }

    public function adminCreate(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:6',
        ]);

        $am = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'is_admin' => true,
            'admin_role' => 'account_manager',
        ]);

        return response()->json(['message' => 'Account Manager created', 'account_manager' => $am], 201);
    }

    public function adminUpdate(Request $request, User $user)
    {
        if (! $user->is_admin || $user->admin_role !== 'account_manager') {
            return response()->json(['error' => 'Not an Account Manager'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,'.$user->id,
            'phone' => 'nullable|string|max:20',
            'password' => 'sometimes|string|min:6',
            'is_active' => 'sometimes|boolean', // using is_admin as active flag for AMs
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        // Map is_active to is_admin for AMs
        if (isset($validated['is_active'])) {
            $validated['is_admin'] = $validated['is_active'];
            unset($validated['is_active']);
        }

        $user->update($validated);

        return response()->json(['message' => 'Account Manager updated', 'account_manager' => $user->fresh()]);
    }

    // ═══════════════════════════════════════
    //  ADMIN: Restaurant Assignment
    // ═══════════════════════════════════════

    public function adminAssignRestaurant(Request $request)
    {
        $validated = $request->validate([
            'account_manager_id' => 'required|uuid|exists:users,id',
            'restaurant_id' => 'required|uuid|exists:restaurants,id',
        ]);

        // Deactivate any existing active assignments for this restaurant
        AccountManagerAssignment::where('restaurant_id', $validated['restaurant_id'])
            ->where('is_active', true)
            ->update(['is_active' => false, 'unassigned_at' => now()]);

        // Create new assignment
        $assignment = AccountManagerAssignment::create([
            'account_manager_id' => $validated['account_manager_id'],
            'restaurant_id' => $validated['restaurant_id'],
            'assigned_by' => $request->user()->id,
            'is_active' => true,
        ]);

        return response()->json(['message' => 'Restaurant assigned', 'assignment' => $assignment], 201);
    }

    public function adminUnassignRestaurant(Request $request, AccountManagerAssignment $assignment)
    {
        $assignment->update(['is_active' => false, 'unassigned_at' => now()]);

        return response()->json(['message' => 'Restaurant unassigned']);
    }

    public function adminAssignments()
    {
        $assignments = AccountManagerAssignment::where('is_active', true)
            ->with(['accountManager:id,name,email', 'restaurant:id,name'])
            ->get()
            ->groupBy('account_manager_id');

        return response()->json(['assignments' => $assignments]);
    }

    public function adminAvailableRestaurants()
    {
        $assignedIds = AccountManagerAssignment::where('is_active', true)
            ->pluck('restaurant_id');

        $restaurants = Restaurant::whereNotIn('id', $assignedIds)
            ->orWhere(function ($q) use ($assignedIds) {
                // Also include restaurants that were previously assigned but now unassigned
                $q->whereIn('id', $assignedIds)
                    ->whereDoesntHave('accountManagerAssignments', fn ($q) => $q->where('is_active', true));
            })
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return response()->json(['restaurants' => $restaurants]);
    }

    // ═══════════════════════════════════════
    //  AM: Dashboard — My Restaurants
    // ═══════════════════════════════════════

    public function myRestaurants(Request $request)
    {
        $amId = $request->user()->id;

        $assignments = AccountManagerAssignment::where('account_manager_id', $amId)
            ->where('is_active', true)
            ->with('restaurant')
            ->get();

        $result = $assignments->map(function ($a) {
            $r = $a->restaurant;
            if (! $r) {
                return null;
            }

            $pendingCount = SponsoredListing::where('restaurant_id', $r->id)
                ->where('approval_status', 'pending')->count()
                + Offer::where('restaurant_id', $r->id)
                    ->where('approval_status', 'pending')->count();

            $activeAds = SponsoredListing::where('restaurant_id', $r->id)
                ->where('status', 'active')->count();

            $activeOffers = Offer::where('restaurant_id', $r->id)
                ->where('status', 'active')->count();

            return [
                'id' => $r->id,
                'name' => $r->name,
                'email' => $r->email,
                'is_active' => $r->is_active,
                'rating' => $r->rating ?? 0,
                'total_orders' => $r->total_orders_completed ?? 0,
                'pending_approvals' => $pendingCount,
                'active_ads' => $activeAds,
                'active_offers' => $activeOffers,
            ];
        })->filter()->values();

        return response()->json(['restaurants' => $result]);
    }

    // ═══════════════════════════════════════
    //  AM: Create Ad on behalf of restaurant
    // ═══════════════════════════════════════

    public function createAd(Request $request, SponsoredListingController $adController)
    {
        $amId = $request->user()->id;
        $restaurantId = $request->input('restaurant_id');

        // Verify AM is assigned to this restaurant
        $assigned = AccountManagerAssignment::where('account_manager_id', $amId)
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->exists();

        if (! $assigned) {
            return response()->json(['error' => 'You are not assigned to this restaurant'], 403);
        }

        // Temporarily set the request user to the restaurant for validation
        $restaurant = Restaurant::findOrFail($restaurantId);
        $originalUser = $request->user();
        $request->setUserResolver(fn () => $restaurant);

        $response = $adController->store($request);

        // Restore original user
        $request->setUserResolver(fn () => $originalUser);

        $ad = json_decode($response->getContent(), true);
        if (isset($ad['ad']['id'])) {
            $listing = SponsoredListing::find($ad['ad']['id']);
            if ($listing) {
                $listing->update([
                    'created_by_type' => 'account_manager',
                    'created_by_id' => $amId,
                    'approval_status' => 'pending',
                    'status' => 'paused', // Cannot go live until approved
                ]);

                $this->logActivity($amId, $restaurantId, 'created_ad', 'sponsored_listing', $listing->id);
                $this->notifyRestaurant($restaurantId, 'New ad pending your approval');
                RequiresRestaurantApproval::dispatch('sponsored_listing', $listing->id, $restaurantId, $request->user()->name);
            }
        }

        return $response;
    }

    // ═══════════════════════════════════════
    //  AM: Create Offer on behalf of restaurant
    // ═══════════════════════════════════════

    public function createOffer(Request $request, OfferController $offerController)
    {
        $amId = $request->user()->id;
        $restaurantId = $request->input('restaurant_id');

        $assigned = AccountManagerAssignment::where('account_manager_id', $amId)
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->exists();

        if (! $assigned) {
            return response()->json(['error' => 'You are not assigned to this restaurant'], 403);
        }

        $restaurant = Restaurant::findOrFail($restaurantId);
        $originalUser = $request->user();
        $request->setUserResolver(fn () => $restaurant);

        $response = $offerController->store($request);

        $request->setUserResolver(fn () => $originalUser);

        $data = json_decode($response->getContent(), true);
        if (isset($data['offer']['id'])) {
            $offer = Offer::find($data['offer']['id']);
            if ($offer) {
                $offer->update([
                    'created_by_type' => 'account_manager',
                    'created_by_id' => $amId,
                    'approval_status' => 'pending',
                ]);

                $this->logActivity($amId, $restaurantId, 'created_offer', 'offer', $offer->id);
                $this->notifyRestaurant($restaurantId, 'New offer pending your approval');
                RequiresRestaurantApproval::dispatch('offer', $offer->id, $restaurantId, $request->user()->name);
            }
        }

        return $response;
    }

    // ═══════════════════════════════════════
    //  RESTAURANT: Approval Queue
    // ═══════════════════════════════════════

    public function pendingApprovals(Request $request)
    {
        $restaurantId = $request->user()->id;

        $pendingAds = SponsoredListing::where('restaurant_id', $restaurantId)
            ->where('approval_status', 'pending')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'type' => 'ad',
                'title' => $a->ad_type === 'card' ? 'Card Ad' : 'PPC Ad',
                'submitted_by' => 'Account Manager',
                'submitted_at' => $a->created_at,
                'approval_status' => $a->approval_status,
                'details' => $a->toArray(),
            ]);

        $pendingOffers = Offer::where('restaurant_id', $restaurantId)
            ->where('approval_status', 'pending')
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'type' => 'offer',
                'title' => $o->title,
                'submitted_by' => 'Account Manager',
                'submitted_at' => $o->created_at,
                'approval_status' => $o->approval_status,
                'details' => $o->toArray(),
            ]);

        $pending = $pendingAds->concat($pendingOffers)->sortByDesc('submitted_at')->values();

        return response()->json(['pending' => $pending]);
    }

    public function approvalHistory(Request $request)
    {
        $restaurantId = $request->user()->id;

        $ads = SponsoredListing::where('restaurant_id', $restaurantId)
            ->where('approval_status', '!=', 'not_required')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id, 'type' => 'ad', 'title' => $a->ad_type === 'card' ? 'Card Ad' : 'PPC Ad',
                'approval_status' => $a->approval_status, 'approved_at' => $a->approved_at,
                'rejection_reason' => $a->rejection_reason,
            ]);

        $offers = Offer::where('restaurant_id', $restaurantId)
            ->where('approval_status', '!=', 'not_required')
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id, 'type' => 'offer', 'title' => $o->title,
                'approval_status' => $o->approval_status, 'approved_at' => $o->approved_at,
                'rejection_reason' => $o->rejection_reason,
            ]);

        return response()->json(['history' => $ads->concat($offers)->sortByDesc('approved_at')->values()]);
    }

    // ═══════════════════════════════════════
    //  AM: My Submissions (items created by this AM)
    // ═══════════════════════════════════════

    public function mySubmissions(Request $request)
    {
        $amId = $request->user()->id;

        $ads = SponsoredListing::where('created_by_type', 'account_manager')
            ->where('created_by_id', $amId)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id, 'type' => 'ad', 'entity_type' => 'sponsored_listing',
                'title' => $a->ad_type === 'card' ? 'Card Ad' : 'PPC Ad',
                'restaurant_id' => $a->restaurant_id,
                'approval_status' => $a->approval_status,
                'status' => $a->approval_status,
                'submitted_at' => $a->created_at,
                'approved_at' => $a->approved_at,
                'rejection_reason' => $a->rejection_reason,
                'description' => null,
            ]);

        $offers = Offer::where('created_by_type', 'account_manager')
            ->where('created_by_id', $amId)
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id, 'type' => 'offer', 'entity_type' => 'offer',
                'title' => $o->title,
                'restaurant_id' => $o->restaurant_id,
                'approval_status' => $o->approval_status,
                'status' => $o->approval_status,
                'submitted_at' => $o->created_at,
                'approved_at' => $o->approved_at,
                'rejection_reason' => $o->rejection_reason,
                'description' => $o->description,
            ]);

        $submissions = $ads->concat($offers)->sortByDesc('submitted_at')->values();

        return response()->json(['submissions' => $submissions]);
    }

    // ═══════════════════════════════════════
    //  RESTAURANT: Send Approval OTP
    // ═══════════════════════════════════════

    public function sendApprovalOtp(Request $request)
    {
        $request->validate([
            'entity_type' => 'required|in:sponsored_listing,offer',
            'entity_id' => 'required|uuid',
        ]);

        $restaurantId = $request->user()->id;
        $entityType = $request->entity_type;
        $entityId = $request->entity_id;

        // Verify the entity exists and belongs to this restaurant
        $entity = null;
        if ($entityType === 'sponsored_listing') {
            $entity = SponsoredListing::where('id', $entityId)->where('restaurant_id', $restaurantId)->where('approval_status', 'pending')->first();
        } else {
            $entity = Offer::where('id', $entityId)->where('restaurant_id', $restaurantId)->where('approval_status', 'pending')->first();
        }

        if (! $entity) {
            return response()->json(['error' => 'Pending item not found'], 404);
        }

        // Rate-limit: allow resend every 60 seconds
        $recent = RestaurantApprovalOtp::where('restaurant_id', $restaurantId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->whereNull('verified_at')
            ->where('created_at', '>', now()->subSeconds(60))
            ->first();

        if ($recent) {
            return response()->json(['error' => 'OTP already sent recently. Please wait.'], 429);
        }

        $rateLimitKey = 'send-otp:'.$request->ip().':'.$restaurantId;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            return response()->json(['error' => "Too many OTP requests. Try again in {$seconds} seconds."], 429);
        }
        RateLimiter::hit($rateLimitKey, 900);

        $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        RestaurantApprovalOtp::create([
            'restaurant_id' => $restaurantId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'otp_hash' => Hash::make($otpCode),
            'expires_at' => now()->addMinutes(10),
        ]);

        $restaurant = Restaurant::find($restaurantId);
        if ($restaurant && $restaurant->email) {
            Mail::to($restaurant->email)->queue(new ApprovalOtpMail($otpCode, $restaurant->name, 10));
        }

        return response()->json([
            'message' => 'OTP sent to registered email.',
            'expires_in_minutes' => 10,
        ]);
    }

    // ═══════════════════════════════════════
    //  RESTAURANT: Verify OTP & Approve/Reject
    // ═══════════════════════════════════════

    public function verifyApprovalOtp(Request $request)
    {
        $validated = $request->validate([
            'entity_type' => 'required|in:sponsored_listing,offer',
            'entity_id' => 'required|uuid',
            'otp' => 'required|string|size:6',
            'action' => 'required|in:approve,reject',
            'rejection_reason' => 'required_if:action,reject|nullable|string|max:500',
        ]);

        $restaurantId = $request->user()->id;
        $entityType = $validated['entity_type'];
        $entityId = $validated['entity_id'];
        $action = $validated['action'];

        // Find valid OTP
        $otpRecord = RestaurantApprovalOtp::where('restaurant_id', $restaurantId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $otpRecord) {
            return response()->json(['error' => 'No valid OTP found. Request a new one.'], 422);
        }

        if ($otpRecord->attempt_count >= 5) {
            return response()->json(['error' => 'Too many incorrect attempts. Request a new OTP.'], 429);
        }

        if (! Hash::check($validated['otp'], $otpRecord->otp_hash)) {
            $otpRecord->increment('attempt_count');
            $remaining = 5 - $otpRecord->fresh()->attempt_count;

            return response()->json(['error' => "Invalid OTP. {$remaining} attempts remaining."], 422);
        }

        if ($action === 'approve') {
            DB::transaction(function () use ($otpRecord, $entityType, $entityId) {
                $otpRecord->update(['verified_at' => now()]);

                if ($entityType === 'sponsored_listing') {
                    $entity = SponsoredListing::find($entityId);
                    if ($entity) {
                        $startDate = Carbon::parse($entity->start_date);
                        $entity->update([
                            'approval_status' => 'approved',
                            'approved_at' => now(),
                            'approved_via' => 'email_otp',
                            'status' => ($startDate->isToday() || $startDate->isPast()) ? 'active' : 'scheduled',
                        ]);
                    }
                } else {
                    $entity = Offer::find($entityId);
                    if ($entity) {
                        $entity->update([
                            'approval_status' => 'approved',
                            'approved_at' => now(),
                            'approved_via' => 'email_otp',
                        ]);
                    }
                }
            });

            $this->notifyCreator($entityType, $entityId, 'approved');

            return response()->json(['message' => 'Approved! The item is now live.', 'approved' => true]);
        } else {
            $otpRecord->update(['verified_at' => now()]);

            if ($entityType === 'sponsored_listing') {
                $entity = SponsoredListing::find($entityId);
            } else {
                $entity = Offer::find($entityId);
            }

            if ($entity) {
                $entity->update([
                    'approval_status' => 'rejected',
                    'rejection_reason' => $validated['rejection_reason'] ?? null,
                ]);
            }

            $this->notifyCreator($entityType, $entityId, 'rejected');

            return response()->json(['message' => 'Rejected. The Account Manager has been notified.', 'approved' => false]);
        }
    }

    // ═══════════════════════════════════════
    //  RESTAURANT: Reject (no OTP required)
    // ═══════════════════════════════════════

    public function rejectApproval(Request $request)
    {
        $validated = $request->validate([
            'entity_type' => 'required|in:sponsored_listing,offer',
            'entity_id' => 'required|uuid',
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $restaurantId = $request->user()->id;
        $entityType = $validated['entity_type'];
        $entityId = $validated['entity_id'];

        if ($entityType === 'sponsored_listing') {
            $entity = SponsoredListing::where('id', $entityId)->where('restaurant_id', $restaurantId)->first();
        } else {
            $entity = Offer::where('id', $entityId)->where('restaurant_id', $restaurantId)->first();
        }

        if (! $entity) {
            return response()->json(['error' => 'Item not found'], 404);
        }

        $entity->update([
            'approval_status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'] ?? null,
        ]);

        $this->notifyCreator($entityType, $entityId, 'rejected');

        return response()->json(['message' => 'Rejected. The Account Manager has been notified.', 'approved' => false]);
    }

    // ═══════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════

    private function logActivity(string $amId, string $restaurantId, string $action, ?string $entityType = null, ?string $entityId = null, ?array $metadata = null)
    {
        AccountManagerActivityLog::create([
            'account_manager_id' => $amId,
            'restaurant_id' => $restaurantId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
        ]);
    }

    private function notifyRestaurant(string $restaurantId, string $message)
    {
        try {
            FcmService::sendToRestaurant($restaurantId, [
                'title' => 'Approval Required',
                'body' => $message,
                'type' => 'approval_pending',
            ]);
        } catch (\Exception $e) {
            Log::warning('Approval notification failed: '.$e->getMessage());
        }
    }

    private function notifyCreator(string $entityType, string $entityId, string $status)
    {
        try {
            $entity = $entityType === 'sponsored_listing'
                ? SponsoredListing::find($entityId)
                : Offer::find($entityId);

            if ($entity && $entity->created_by_type === 'account_manager' && $entity->created_by_id) {
                FcmService::sendToUser($entity->created_by_id, [
                    'title' => "Item {$status}",
                    'body' => "Your submission has been {$status} by the restaurant.",
                    'type' => "approval_{$status}",
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Creator notification failed: '.$e->getMessage());
        }
    }
}
