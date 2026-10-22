<?php

namespace App\Http\Controllers\Api;

use App\Models\PayoutCycle;
use App\Models\Restaurant;
use App\Services\PayoutCalculationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PayoutController extends Controller
{
    public function __construct(
        private PayoutCalculationService $payoutService
    ) {}

    public function index(Request $request)
    {
        $request->validate(['restaurant_id' => 'sometimes|uuid']);

        $cycles = PayoutCycle::with(['orderLines', 'deductions', 'additions'])
            ->when($request->restaurant_id, fn ($q) => $q->where('restaurant_id', $request->restaurant_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->from_date, fn ($q) => $q->where('cycle_end', '>=', $request->from_date))
            ->when($request->to_date, fn ($q) => $q->where('cycle_start', '<=', $request->to_date))
            ->orderBy('cycle_start', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($cycles);
    }

    public function show(PayoutCycle $cycle)
    {
        $cycle->load(['orderLines.order.items.menuItem', 'deductions', 'additions', 'enteredBy', 'approvedBy', 'restaurant']);
        return response()->json(['cycle' => $cycle]);
    }

    public function generate(Request $request)
    {
        $request->validate([
            'restaurant_id' => 'required|uuid|exists:restaurants,id',
            'cycle_start' => 'required|date',
            'cycle_end' => 'required|date|after:cycle_start',
        ]);

        $existing = PayoutCycle::where('restaurant_id', $request->restaurant_id)
            ->where('cycle_start', $request->cycle_start)
            ->where('cycle_end', $request->cycle_end)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Cycle already exists', 'cycle' => $existing], 200);
        }

        $restaurant = Restaurant::findOrFail($request->restaurant_id);
        $cycle = $this->payoutService->generateWeeklyCycle(
            $restaurant,
            \Carbon\Carbon::parse($request->cycle_start),
            \Carbon\Carbon::parse($request->cycle_end)
        );

        return response()->json(['message' => 'Cycle generated', 'cycle' => $cycle->load(['orderLines', 'deductions', 'additions'])], 201);
    }

    public function markPaid(Request $request, PayoutCycle $cycle)
    {
        $request->validate([
            'utr_number' => 'required|string|max:100',
            'payment_mode' => 'required|in:NEFT,RTGS,IMPS,UPI',
            'amount_paid' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'payment_notes' => 'nullable|string|max:500',
            'override_reason' => 'required_if:amount_paid,!'.$cycle->net_payable.'|nullable|string|max:500',
        ]);

        $cycle = $this->payoutService->markAsPaid(
            $cycle,
            $request->utr_number,
            $request->payment_mode,
            $request->amount_paid,
            $request->payment_notes,
            $request->user()
        );

        return response()->json(['message' => 'Payout marked as paid', 'cycle' => $cycle]);
    }

    public function addDeduction(Request $request, PayoutCycle $cycle)
    {
        $request->validate([
            'type' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0',
            'reference_type' => 'nullable|string|max:100',
            'reference_id' => 'nullable|uuid',
            'notes' => 'nullable|string|max:500',
        ]);

        $deduction = $cycle->deductions()->create([
            ...$request->only(['type', 'amount', 'reference_type', 'reference_id', 'notes']),
            'added_by' => $request->user()->id,
        ]);

        $cycle->increment('total_deductions', $request->amount);
        $cycle->net_payable = $this->payoutService->getNetPayable($cycle->fresh(['deductions', 'additions']));
        $cycle->save();

        return response()->json(['message' => 'Deduction added', 'deduction' => $deduction]);
    }

    public function addAddition(Request $request, PayoutCycle $cycle)
    {
        $request->validate([
            'type' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0',
            'reference_type' => 'nullable|string|max:100',
            'reference_id' => 'nullable|uuid',
            'notes' => 'required|string|max:500',
        ]);

        $addition = $cycle->additions()->create([
            ...$request->only(['type', 'amount', 'reference_type', 'reference_id', 'notes']),
            'added_by' => $request->user()->id,
        ]);

        $cycle->increment('total_additions', $request->amount);
        $cycle->net_payable = $this->payoutService->getNetPayable($cycle->fresh(['deductions', 'additions']));
        $cycle->save();

        return response()->json(['message' => 'Addition added', 'addition' => $addition]);
    }

    public function search(Request $request)
    {
        $request->validate(['q' => 'required|string|min:2|max:100']);

        $restaurants = Restaurant::where('is_active', true)
            ->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->q}%")
                  ->orWhere('email', 'like', "%{$request->q}%")
                  ->orWhere('id', 'like', "%{$request->q}%");
            })
            ->limit(20)
            ->get(['id', 'name', 'address', 'email']);

        return response()->json(['data' => $restaurants]);
    }

    public function vendorIndex(Request $request)
    {
        $restaurant = $request->user();

        $cycles = PayoutCycle::with(['orderLines', 'deductions', 'additions'])
            ->where('restaurant_id', $restaurant->id)
            ->when($request->from_date, fn ($q) => $q->where('cycle_end', '>=', $request->from_date))
            ->when($request->to_date, fn ($q) => $q->where('cycle_start', '<=', $request->to_date))
            ->orderBy('cycle_start', 'desc')
            ->paginate($request->per_page ?? 20);

        $balance = \App\Models\RestaurantPayoutBalance::where('restaurant_id', $restaurant->id)->first();

        return response()->json([
            'cycles' => $cycles,
            'current_balance' => $balance?->running_balance ?? 0,
        ]);
    }

    public function vendorShow(Request $request, PayoutCycle $cycle)
    {
        $restaurant = $request->user();

        if ($cycle->restaurant_id !== $restaurant->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $cycle->load(['orderLines.order.items.menuItem', 'deductions', 'additions']);

        return response()->json(['cycle' => $cycle]);
    }
}
