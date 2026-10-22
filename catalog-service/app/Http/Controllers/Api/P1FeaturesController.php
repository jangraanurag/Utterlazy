<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashReconciliation;
use App\Models\DeliveryPass;
use App\Models\EvChargingStation;
use App\Models\FestivalCampaign;
use App\Models\Order;
use App\Models\Rider;
use App\Models\StaffShift;
use App\Models\Supplier;
use App\Models\SurgePricingConfig;
use Illuminate\Http\Request;

class P1FeaturesController extends Controller
{
    // ─── Sachet Pricing / Delivery Passes ───

    public function purchasePass(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:daily,weekly',
            'price' => 'required|numeric|min:10|max:200',
        ]);

        $pass = DeliveryPass::create([
            'user_id' => $request->user()->id,
            'type' => $validated['type'],
            'price' => $validated['price'],
            'valid_from' => today(),
            'valid_until' => $validated['type'] === 'daily' ? today()->addDay() : today()->addWeek(),
            'status' => 'active',
        ]);

        return response()->json(['message' => 'Pass purchased!', 'pass' => $pass], 201);
    }

    public function activePass(Request $request)
    {
        $pass = DeliveryPass::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->whereDate('valid_until', '>=', today())
            ->first();

        return response()->json(['pass' => $pass]);
    }

    // ─── Regional Festivals ───

    public function festivals()
    {
        $festivals = FestivalCampaign::where('is_active', true)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->get();

        return response()->json(['festivals' => $festivals]);
    }

    // ─── Surge Pricing ───

    public function surgeConfig()
    {
        $config = SurgePricingConfig::where('is_active', true)->first();

        return response()->json(['surge' => $config]);
    }

    public function currentSurge(Request $request)
    {
        $hour = now()->hour;
        $multiplier = 1.0;

        // Peak hours 12-2 PM and 7-10 PM
        if (($hour >= 12 && $hour <= 14) || ($hour >= 19 && $hour <= 22)) {
            $multiplier = 1.3;
        }

        // Rain check (simplified)
        $weather = $request->input('weather');
        if ($weather === 'rainy') {
            $multiplier += 0.2;
        }

        // Demand-based
        $pendingOrders = Order::whereIn('status', ['pending', 'confirmed', 'preparing'])->count();
        $onlineRiders = Rider::where('is_online', true)->count();
        if ($onlineRiders > 0 && ($pendingOrders / $onlineRiders) > 3) {
            $multiplier += 0.3;
        }

        return response()->json([
            'multiplier' => round(min($multiplier, 2.0), 2),
            'is_surge' => $multiplier > 1.0,
            'reason' => $multiplier > 1.0 ? 'High demand period' : null,
        ]);
    }

    // ─── Suppliers ───

    public function suppliers(Request $request)
    {
        $restaurant = $request->user();

        return response()->json([
            'suppliers' => Supplier::where('restaurant_id', $restaurant->id)->get(),
        ]);
    }

    public function createSupplier(Request $request)
    {
        $restaurant = $request->user();
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|in:vegetables,meat,dairy,bakery,other',
            'contact_person' => 'sometimes|string',
            'phone' => 'sometimes|string',
            'email' => 'sometimes|email',
            'items' => 'sometimes|array',
        ]);

        $supplier = Supplier::create([
            'restaurant_id' => $restaurant->id,
            'name' => $validated['name'],
            'category' => $validated['category'],
            'contact_person' => $validated['contact_person'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'items' => $validated['items'] ?? null,
        ]);

        return response()->json(['message' => 'Supplier added!', 'supplier' => $supplier], 201);
    }

    // ─── Staff Shifts ───

    public function staffShifts(Request $request)
    {
        $restaurant = $request->user();
        $shifts = StaffShift::where('restaurant_id', $restaurant->id)
            ->whereDate('date', $request->date ?? today())
            ->with('staff:id,name,role')
            ->get();

        return response()->json(['shifts' => $shifts]);
    }

    public function clockIn(Request $request)
    {
        $validated = $request->validate([
            'shift_id' => 'required|exists:staff_shifts,id',
        ]);

        $shift = StaffShift::findOrFail($validated['shift_id']);
        $shift->update(['status' => 'clocked_in', 'clocked_in_at' => now()]);

        return response()->json(['message' => 'Clocked in!', 'shift' => $shift]);
    }

    public function clockOut(Request $request)
    {
        $validated = $request->validate([
            'shift_id' => 'required|exists:staff_shifts,id',
        ]);

        $shift = StaffShift::findOrFail($validated['shift_id']);
        $shift->update(['status' => 'clocked_out', 'clocked_out_at' => now()]);

        return response()->json(['message' => 'Clocked out!', 'shift' => $shift]);
    }

    // ─── EV Charging ───

    public function evStations()
    {
        return response()->json([
            'stations' => EvChargingStation::where('is_active', true)->get(),
        ]);
    }

    public function nearestEvStations(Request $request)
    {
        $validated = $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $stations = EvChargingStation::where('is_active', true)->get();
        $stations = $stations->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'address' => $s->address,
            'distance_km' => round($this->haversine($validated['lat'], $validated['lng'], $s->latitude, $s->longitude), 2),
            'available_slots' => $s->available_slots,
        ])->sortBy('distance_km')->values();

        return response()->json(['stations' => $stations]);
    }

    // ─── Cash Reconciliation ───

    public function cashReconciliation(Request $request)
    {
        $rider = $request->user();
        $date = $request->date ?? today();

        $codOrders = Order::where('rider_id', $rider->id)
            ->where('payment_mode', 'cod')
            ->where('status', 'delivered')
            ->whereDate('delivered_at', $date)
            ->get();

        $expected = $codOrders->sum('grand_total');

        $reconciliation = CashReconciliation::firstOrCreate(
            ['rider_id' => $rider->id, 'date' => $date],
            ['expected_amount' => $expected, 'collected_amount' => 0, 'deposited_amount' => 0, 'difference' => 0]
        );

        return response()->json([
            'reconciliation' => $reconciliation,
            'cod_orders' => $codOrders->map(fn ($o) => ['id' => $o->id, 'amount' => $o->grand_total]),
        ]);
    }

    public function submitCash(Request $request)
    {
        $rider = $request->user();
        $validated = $request->validate([
            'date' => 'required|date',
            'collected_amount' => 'required|numeric|min:0',
            'deposited_amount' => 'required|numeric|min:0',
        ]);

        $recon = CashReconciliation::where('rider_id', $rider->id)
            ->whereDate('date', $validated['date'])->firstOrFail();

        $diff = $validated['collected_amount'] - $validated['deposited_amount'];
        $recon->update([
            'collected_amount' => $validated['collected_amount'],
            'deposited_amount' => $validated['deposited_amount'],
            'difference' => $diff,
            'status' => abs($diff) < 10 ? 'reconciled' : 'discrepancy',
            'notes' => abs($diff) >= 10 ? "Difference of ₹{$diff} needs review" : null,
        ]);

        return response()->json(['message' => 'Cash submitted!', 'reconciliation' => $recon]);
    }

    protected function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
