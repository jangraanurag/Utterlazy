<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Rider;
use App\Models\RiderAssignment;
use App\Models\RiderDocument;
use App\Models\RiderPayout;
use App\Models\RiderPayoutCycle;
use App\Models\RiderRating;
use App\Models\RiderSosAlert;
use App\Models\User;
use App\Services\RiderDashboardService;
use App\Services\RiderPayCalculator;
use App\Services\FcmService;
use App\Services\RestaurantRankingService;
use App\Services\RiderAssignmentService;
use App\Services\RiderIncentiveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class RiderController extends Controller
{
    /**
     * Rider self-registration.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:riders,email',
            'phone' => 'required|string|unique:riders,phone',
            'password' => 'required|string|min:6',
            'vehicle_type' => 'sometimes|in:bicycle,scooter,bike,car',
            'vehicle_number' => 'sometimes|string',
        ]);

        $rider = Rider::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => $validated['password'],
            'vehicle_type' => $validated['vehicle_type'] ?? 'bike',
            'vehicle_number' => $validated['vehicle_number'] ?? null,
        ]);

        $token = $rider->createToken('rider-token')->plainTextToken;

        return response()->json([
            'message' => 'Rider registered successfully! Awaiting admin verification.',
            'rider' => $rider,
            'token' => $token,
        ], 201);
    }

    /**
     * Rider login.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $rider = Rider::where('email', $request->email)->first();

        if (! $rider || ! Hash::check($request->password, $rider->password)) {
            return response()->json(['error' => 'Invalid credentials.'], 401);
        }

        $token = $rider->createToken('rider-token')->plainTextToken;

        return response()->json([
            'rider' => $rider,
            'token' => $token,
        ]);
    }

    /**
     * Rider profile with today's stats.
     */
    public function me(Request $request)
    {
        $rider = $request->user();

        return response()->json([
            'rider' => $rider,
            'today' => [
                'deliveries' => $rider->todaysDeliveries(),
                'earnings' => $rider->todaysEarnings(),
            ],
            'unsettled' => $rider->unsettledAmount(),
            'surge' => RiderAssignmentService::getSurgeMultiplier(),
        ]);
    }

    /**
     * Update live GPS location (only when online).
     */
    public function updateLocation(Request $request)
    {
        $rider = $request->user();

        if (! $rider->is_online) {
            return response()->json(['error' => 'Go online before sharing location.'], 422);
        }

        $request->validate([
            'lat' => 'required_without:latitude|numeric',
            'lng' => 'required_without:longitude|numeric',
            'latitude' => 'required_without:lat|numeric',
            'longitude' => 'required_without:lng|numeric',
        ]);

        $newLat = (float) ($request->lat ?? $request->latitude);
        $newLng = (float) ($request->lng ?? $request->longitude);

        $guard = new \App\Services\Gps\GpsSpeedGuard();
        $plausible = $guard->isPlausible(
            $rider->latitude !== null ? (float) $rider->latitude : null,
            $rider->longitude !== null ? (float) $rider->longitude : null,
            $rider->updated_at,
            $newLat,
            $newLng,
            now(),
        );

        if (! $plausible) {
            $rider->update([
                'gps_anomaly_count' => ((int) $rider->gps_anomaly_count) + 1,
                'gps_flagged_at' => now(),
            ]);

            return response()->json([
                'error' => 'Location jump rejected (implausible speed). Assignment is on hold until movement normalises.',
                'code' => 'gps_anomaly',
            ], 422);
        }

        $rider->update([
            'latitude' => $newLat,
            'longitude' => $newLng,
            'gps_anomaly_count' => 0,
        ]);

        return response()->json(['message' => 'Location updated.']);
    }

    /**
     * Toggle online/offline status.
     */
    public function toggleOnline(Request $request)
    {
        $rider = $request->user();

        $validated = $request->validate([
            'is_online' => 'required|boolean',
        ]);

        $newStatus = (bool) $validated['is_online'];

        // If going offline, must not have active order
        if (! $newStatus && $rider->current_order_id) {
            return response()->json(['error' => 'Complete your current delivery before going offline.'], 422);
        }

        // If going online, must have GPS location
        if ($newStatus && (! $rider->latitude || ! $rider->longitude)) {
            return response()->json(['error' => 'Enable location sharing before going online.'], 422);
        }

        $rider->update([
            'is_online' => $newStatus,
            'latitude' => $newStatus ? $rider->latitude : null,
            'longitude' => $newStatus ? $rider->longitude : null,
        ]);

        // If going online, check pending assignment queue
        if ($newStatus && ! $rider->current_order_id) {
            try {
                $pendingAssignment = RiderAssignmentService::checkPendingForRider($rider);
                if ($pendingAssignment) {
                    Log::info("Pending assignment dispatched to rider {$rider->id} for order {$pendingAssignment->order_id}");
                }
            } catch (\Throwable $e) {
                Log::error("Pending assignment check failed: {$e->getMessage()}");
            }
        }

        return response()->json([
            'message' => $newStatus ? 'You are now online' : 'You are now offline',
            'is_online' => $newStatus,
        ]);
    }

    /**
     * Get current pending assignment (if any).
     */
    public function currentAssignment(Request $request)
    {
        $rider = $request->user();

        $assignment = RiderAssignment::where('rider_id', $rider->id)
            ->where('status', 'pending')
            ->with(['order.restaurant', 'order.items.menuItem', 'order.user:id,name,phone'])
            ->latest('assigned_at')
            ->first();

        if (! $assignment) {
            // Also check if rider has an active order
            $activeOrder = null;
            if ($rider->current_order_id) {
                $activeOrder = $rider->currentOrder()
                    ->with(['restaurant', 'items.menuItem', 'user:id,name,phone'])
                    ->first();
            }

            return response()->json([
                'assignment' => null,
                'active_order' => $activeOrder,
            ]);
        }

        $assignmentArr = $assignment->toArray();
        $assignmentArr['distance_eta'] = RiderAssignmentService::distanceEtaPackage($assignment->order, $rider);

        return response()->json([
            'assignment' => $assignmentArr,
            'active_order' => null,
        ]);
    }

    /**
     * Accept assignment.
     */
    public function acceptAssignment(RiderAssignment $assignment, Request $request)
    {
        $rider = $request->user();

        if ($assignment->rider_id !== $rider->id) {
            return response()->json(['error' => 'Not your assignment.'], 403);
        }

        if ($assignment->status !== 'pending') {
            return response()->json(['error' => 'Assignment already responded to.'], 422);
        }

        RiderAssignmentService::accept($assignment);

        return response()->json([
            'message' => 'Assignment accepted! Head to the restaurant. 🏍️',
            'assignment' => $assignment->fresh()->load('order.restaurant'),
        ]);
    }

    /**
     * Reject assignment → auto-assigns next rider.
     */
    public function rejectAssignment(RiderAssignment $assignment, Request $request)
    {
        $rider = $request->user();

        if ($assignment->rider_id !== $rider->id) {
            return response()->json(['error' => 'Not your assignment.'], 403);
        }

        if ($assignment->status !== 'pending') {
            return response()->json(['error' => 'Assignment already responded to.'], 422);
        }

        $validated = $request->validate([
            'reason' => 'sometimes|in:too_far,vehicle_breakdown,safety,other',
            'notes' => 'required_if:reason,other|nullable|string|min:10|max:120',
        ]);

        // Breakdown before pickup is never a strike: it is recorded and the order moves on.
        $assignment->update([
            'rejection_reason' => $validated['reason'] ?? 'other',
        ]);

        $nextAssignment = RiderAssignmentService::reject($assignment);

        return response()->json([
            'message' => 'Assignment rejected.',
            'next_rider_assigned' => $nextAssignment ? true : false,
        ]);
    }

    /**
     * Earnings summary with history.
     */
    public function earnings(Request $request)
    {
        $rider = $request->user();

        $payouts = RiderPayout::where('rider_id', $rider->id)
            ->with('order:id,restaurant_id,grand_total,status,created_at')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'total_earnings' => $rider->total_earnings,
            'total_deliveries' => $rider->total_deliveries,
            'unsettled' => $rider->unsettledAmount(),
            'payouts' => $payouts,
        ]);
    }

    /**
     * Today's breakdown.
     */
    public function earningsToday(Request $request)
    {
        $rider = $request->user();

        $todayPayouts = RiderPayout::where('rider_id', $rider->id)
            ->whereDate('created_at', today())
            ->with('order:id,restaurant_id,grand_total')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'date' => today()->toDateString(),
            'deliveries' => $todayPayouts->count(),
            'breakdown' => [
                'base' => round($todayPayouts->sum('base_pay'), 2),
                'distance' => round($todayPayouts->sum('distance_pay'), 2),
                'surge' => round($todayPayouts->sum('surge_pay'), 2),
                'wait' => round($todayPayouts->sum('wait_pay'), 2),
                'tips' => round($todayPayouts->sum('tip_amount'), 2),
                'incentives' => round($todayPayouts->sum('incentive_bonus'), 2),
                'penalties' => round($todayPayouts->sum('penalty'), 2),
                'cash_collected' => round($todayPayouts->sum('cash_collected'), 2),
            ],
            'total' => round($todayPayouts->sum('total_payout'), 2),
            'payouts' => $todayPayouts,
        ]);
    }

    /**
     * Rate a rider after delivery (triggers EMA update).
     */
    public function rateDelivery(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|uuid',
            'rider_id' => 'required|uuid',
            'rating' => 'required|integer|min:1|max:5',
            'feedback' => 'sometimes|string|max:500',
        ]);

        $order = Order::where('id', $validated['order_id'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $order) {
            return response()->json(['error' => 'Order not found or not yours.'], 404);
        }

        $rider = Rider::find($validated['rider_id']);
        if (! $rider) {
            return response()->json(['error' => 'Rider not found.'], 404);
        }

        DB::transaction(function () use ($validated, $rider, $order) {
            RiderRating::updateOrCreate(
                [
                    'order_id' => $validated['order_id'],
                    'user_id' => $order->user_id,
                ],
                [
                    'rider_id' => $validated['rider_id'],
                    'rating' => $validated['rating'],
                    'feedback' => $validated['feedback'] ?? null,
                    'created_at' => now(),
                ]
            );

            $rider->recalculateRating();
            RiderAssignmentService::updateEmaRating($rider, $validated['rating']);
        });

        return response()->json(['message' => 'Rider rating submitted.']);
    }

    /**
     * Rider rates the restaurant at pickup time.
     */
    public function rateRestaurant(Request $request)
    {
        $rider = $request->user();

        $validated = $request->validate([
            'order_id' => 'required|uuid',
            'restaurant_id' => 'required|uuid',
            'rating' => 'required|integer|min:1|max:5',
            'feedback' => 'sometimes|string|max:500',
        ]);

        $order = Order::where('id', $validated['order_id'])
            ->where('rider_id', $rider->id)
            ->first();

        if (! $order) {
            return response()->json(['error' => 'Order not found or not assigned to you.'], 404);
        }

        if ($order->restaurant_id !== $validated['restaurant_id']) {
            return response()->json(['error' => 'Restaurant does not match this order.'], 422);
        }

        $order->update([
            'rider_rating_for_restaurant' => $validated['rating'],
            'rider_feedback_for_restaurant' => $validated['feedback'] ?? null,
        ]);

        // Update EMA rider rating (PDF §3.3)
        $restaurant = $order->restaurant;
        if ($restaurant) {
            RestaurantRankingService::updateRiderEma($restaurant, $validated['rating']);
        }

        return response()->json(['message' => 'Restaurant rating submitted.']);
    }

    /**
     * Get orders pending rider-to-restaurant rating.
     */
    public function pendingRestaurantRatings(Request $request)
    {
        $rider = $request->user();

        $orders = Order::where('rider_id', $rider->id)
            ->whereNull('rider_rating_for_restaurant')
            ->whereIn('status', ['picked_up', 'out_for_delivery', 'delivered', 'completed'])
            ->whereNull('rider_rating_for_restaurant')
            ->with(['restaurant:id,name,logo_url'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json(['data' => $orders]);
    }

    /**
     * Upload a verification document (Aadhaar, DL, PAN, selfie, RC, insurance).
     */
    public function uploadDocument(Request $request)
    {
        $rider = $request->user();

        $validated = $request->validate([
            'document_type' => 'required|in:aadhaar_front,aadhaar_back,driving_license_front,driving_license_back,pan_card,selfie,vehicle_rc,insurance',
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'document_number' => 'sometimes|string|max:50',
        ]);

        $path = $request->file('file')->store("rider-documents/{$rider->id}", 'public');

        $document = RiderDocument::updateOrCreate(
            [
                'rider_id' => $rider->id,
                'document_type' => $validated['document_type'],
            ],
            [
                'file_path' => $path,
                'document_number' => $validated['document_number'] ?? null,
                'status' => 'pending',
                'rejection_reason' => null,
                'verified_at' => null,
                'verified_by' => null,
            ]
        );

        $requiredTypes = ['aadhaar_front', 'aadhaar_back', 'driving_license_front', 'pan_card', 'selfie'];
        $uploaded = RiderDocument::where('rider_id', $rider->id)->whereIn('document_type', $requiredTypes)->count();
        if ($uploaded >= count($requiredTypes) && $rider->verification_status === 'unverified') {
            $rider->update(['verification_status' => 'pending']);
        }

        return response()->json([
            'message' => 'Document uploaded. Awaiting verification.',
            'document' => $document,
            'verification_status' => $rider->fresh()->verification_status,
        ], 201);
    }

    /**
     * Get all uploaded documents + verification status.
     */
    public function documents(Request $request)
    {
        $rider = $request->user();

        $documents = RiderDocument::where('rider_id', $rider->id)->get();
        $required = ['aadhaar_front', 'aadhaar_back', 'driving_license_front', 'driving_license_back', 'pan_card', 'selfie', 'vehicle_rc', 'insurance'];

        $statusMap = [];
        foreach ($required as $type) {
            $doc = $documents->firstWhere('document_type', $type);
            $statusMap[$type] = $doc ? [
                'status' => $doc->status,
                'file_path' => $doc->file_path,
                'rejection_reason' => $doc->rejection_reason,
            ] : ['status' => 'not_uploaded', 'file_path' => null, 'rejection_reason' => null];
        }

        return response()->json([
            'verification_status' => $rider->verification_status,
            'is_verified' => $rider->is_verified,
            'verification_notes' => $rider->verification_notes,
            'documents' => $statusMap,
        ]);
    }

    /**
     * Re-upload a rejected document.
     */
    public function reuploadDocument(Request $request)
    {
        return $this->uploadDocument($request);
    }

    public function incentives(Request $request)
    {
        $rider = $request->user();
        $incentiveService = app(RiderIncentiveService::class);

        return response()->json($incentiveService->getFullProfile($rider));
    }

    public function batchOrders(Request $request)
    {
        $rider = $request->user();

        $currentOrder = Order::where('rider_id', $rider->id)
            ->whereIn('status', ['ready_for_pickup', 'out_for_delivery'])
            ->first();

        if (! $currentOrder) {
            return response()->json(['batchable' => [], 'message' => 'No active order to batch with.']);
        }

        $batchable = RiderAssignmentService::findBatchableOrders($currentOrder);

        return response()->json([
            'batchable' => $batchable,
            'current_order_id' => $currentOrder->id,
            'current_restaurant' => $currentOrder->restaurant?->name,
            'batch_count' => count($batchable),
            'message' => count($batchable) > 0
                ? 'You can pick up '.count($batchable).' more orders in this trip!'
                : 'No nearby batchable orders.',
        ]);
    }

    // ─── Safety Suite ───

    public function sos(Request $request)
    {
        $rider = $request->user();

        $validated = $request->validate([
            'type' => 'sometimes|in:sos,accident,breakdown,theft',
            'message' => 'sometimes|string|max:500',
            'latitude' => 'required_with:longitude|numeric',
            'longitude' => 'required_with:latitude|numeric',
        ]);

        $alert = RiderSosAlert::create([
            'rider_id' => $rider->id,
            'type' => $validated['type'] ?? 'sos',
            'message' => $validated['message'] ?? 'Rider needs immediate assistance!',
            'latitude' => $validated['latitude'] ?? $rider->latitude,
            'longitude' => $validated['longitude'] ?? $rider->longitude,
            'status' => 'active',
        ]);

        // Notify admin ops team (log + broadcast)
        Log::warning("RIDER SOS: {$rider->name} ({$rider->phone}) — {$alert->type} at lat:{$alert->latitude}, lng:{$alert->longitude}");

        // Notify emergency contacts via FCM
        if ($rider->emergency_contacts) {
            $fcm = app(FcmService::class);
            foreach ($rider->emergency_contacts as $contact) {
                $fcm->sendToUser(
                    User::where('phone', $contact['phone'])->first(),
                    'customer',
                    ['type' => 'rider_sos', 'sos_id' => $alert->id, 'screen' => 'rider_safety'],
                    '🚨 Emergency Alert from '.$rider->name,
                    "Your rider {$rider->name} triggered an SOS. Location shared. Track now."
                );
            }
        }

        return response()->json([
            'message' => 'SOS alert sent! Help is on the way.',
            'alert' => $alert,
        ]);
    }

    public function resolveSos(Request $request)
    {
        $rider = $request->user();
        RiderSosAlert::where('rider_id', $rider->id)
            ->where('status', 'active')
            ->update(['status' => 'resolved', 'resolved_at' => now()]);

        return response()->json(['message' => 'SOS resolved. Stay safe!']);
    }

    public function saveEmergencyContacts(Request $request)
    {
        $rider = $request->user();

        $validated = $request->validate([
            'contacts' => 'required|array|min:1|max:3',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.phone' => 'required|string|max:15',
            'contacts.*.relation' => 'required|string|max:50',
        ]);

        $rider->update(['emergency_contacts' => $validated['contacts']]);

        return response()->json([
            'message' => 'Emergency contacts saved. They will be notified in case of SOS.',
            'contacts' => $rider->emergency_contacts,
        ]);
    }

    public function shareRide(Request $request)
    {
        $rider = $request->user();

        $validated = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        if ($validated['enabled']) {
            $shareLink = url("/rider-track/{$rider->id}?".http_build_query([
                'lat' => $rider->latitude,
                'lng' => $rider->longitude,
                't' => now()->timestamp,
            ]));

            $rider->update([
                'safety_ride_sharing_enabled' => true,
                'share_link' => $shareLink,
                'share_expires_at' => now()->addHours(2),
            ]);
        } else {
            $rider->update([
                'safety_ride_sharing_enabled' => false,
                'share_link' => null,
                'share_expires_at' => null,
            ]);
        }

        return response()->json([
            'message' => $validated['enabled'] ? 'Ride sharing enabled. Share the link with family!' : 'Ride sharing disabled.',
            'share_link' => $rider->share_link,
        ]);
    }

    // ─── Zone Preference ───

    public function saveZonePreference(Request $request)
    {
        $rider = $request->user();

        $validated = $request->validate([
            'zones' => 'required|array',
            'zones.*.name' => 'required|string',
            'zones.*.lat' => 'required|numeric',
            'zones.*.lng' => 'required|numeric',
            'zones.*.radius_km' => 'required|numeric|min:1|max:20',
        ]);

        $rider->update(['preferred_zones' => $validated['zones']]);

        return response()->json([
            'message' => 'Zone preferences saved. You will get priority for orders in these areas.',
            'zones' => $rider->preferred_zones,
        ]);
    }

    public function getZonePreference(Request $request)
    {
        $rider = $request->user();

        return response()->json([
            'zones' => $rider->preferred_zones ?? [],
        ]);
    }

    // ─── Instant UPI Payout ───

    public function saveUpiId(Request $request)
    {
        $rider = $request->user();

        $validated = $request->validate([
            'upi_id' => 'required|string|max:50',
        ]);

        $changed = $rider->upi_id !== null && $rider->upi_id !== $validated['upi_id'];
        $rider->update(['upi_id' => $validated['upi_id']]);

        if ($changed) {
            $rider->tokens()->delete();

            return response()->json([
                'message' => 'UPI ID changed. Please log in again to secure payouts.',
                'upi_id' => $rider->upi_id,
                'session_revoked' => true,
            ]);
        }

        return response()->json([
            'message' => 'UPI ID saved! You can now request instant payouts.',
            'upi_id' => $rider->upi_id,
        ]);
    }

    public function requestInstantPayout(Request $request)
    {
        $rider = $request->user();

        if (! $rider->upi_id) {
            return response()->json(['error' => 'Please save a UPI ID first.'], 422);
        }

        $unsettledAmount = $rider->unsettledAmount();
        if ($unsettledAmount < 50) {
            return response()->json(['error' => 'Minimum ₹50 required for payout.'], 422);
        }

        // Mark unsettled payouts as settled
        RiderPayout::where('rider_id', $rider->id)
            ->where('is_settled', false)
            ->update([
                'is_settled' => true,
                'settled_at' => now(),
            ]);

        $rider->total_earnings = max(0, $rider->total_earnings - $unsettledAmount);
        $rider->save();

        // Log the payout for admin processing
        Log::info("INSTANT PAYOUT: Rider {$rider->name} ({$rider->phone}) — ₹{$unsettledAmount} to UPI {$rider->upi_id}");

        return response()->json([
            'message' => "Instant payout of ₹{$unsettledAmount} requested to {$rider->upi_id}. Will be processed shortly.",
            'amount' => $unsettledAmount,
            'upi_id' => $rider->upi_id,
        ]);
    }

    public function updateDeliveryMode(Request $request)
    {
        $rider = $request->user();

        $validated = $request->validate([
            'delivery_mode' => 'required|in:bicycle,walking,scooter,bike,car',
        ]);

        $rider->update(['delivery_mode' => $validated['delivery_mode']]);

        $maxDistance = match ($validated['delivery_mode']) {
            'walking' => 1,
            'bicycle' => 3,
            default => 10,
        };

        return response()->json([
            'message' => "Delivery mode set to {$validated['delivery_mode']}.",
            'delivery_mode' => $rider->delivery_mode,
            'max_distance_km' => $maxDistance,
        ]);
    }

    // ─── Dashboard (§2) ───
    public function dashboard(Request $request)
    {
        $rider = $request->user();

        $validated = $request->validate([
            'period' => 'sometimes|in:week,month,custom',
            'start_date' => 'required_if:period,custom|date|nullable',
            'end_date' => 'required_if:period,custom|date|nullable',
        ]);

        $data = RiderDashboardService::getDashboard(
            $rider->id,
            $validated['period'] ?? 'week',
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null,
        );

        return response()->json($data);
    }

    // ─── History (§3) ───
    public function history(Request $request)
    {
        $rider = $request->user();

        $data = RiderDashboardService::getHistory(
            $rider->id,
            (int) ($request->get('page', 1)),
        );

        return response()->json($data);
    }

    // ─── Pay Calculator (for reference/testing) ───
    public function calculatePay(Request $request)
    {
        $request->validate([
            'distance_km' => 'required|numeric|min:0',
        ]);

        $result = RiderPayCalculator::calculate((float) $request->distance_km);

        return response()->json($result);
    }

    // ─── Payout Cycles (§5) ───
    public function payoutCycles(Request $request)
    {
        $rider = $request->user();

        $cycles = RiderPayoutCycle::where('rider_id', $rider->id)
            ->orderBy('cycle_date', 'desc')
            ->paginate(20);

        return response()->json($cycles);
    }

    public function payoutCycleDetail(Request $request, RiderPayoutCycle $cycle)
    {
        $rider = $request->user();

        if ($cycle->rider_id !== $rider->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $cycle->load(['enteredBy:id,name', 'approvedBy:id,name']);

        // Include order-level payouts for this cycle's date
        $orders = RiderPayout::where('rider_id', $rider->id)
            ->whereDate('created_at', $cycle->cycle_date)
            ->with('order:id,restaurant_id,delivery_distance_km,created_at')
            ->get();

        return response()->json([
            'cycle' => $cycle,
            'orders' => $orders,
        ]);
    }
}
