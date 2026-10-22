<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OpsShift;
use App\Services\AuditLogService;
use App\Services\ShiftRotationPlanner;
use App\Services\Shifts\RotationGapIncidentService;
use App\Services\ShiftService;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function __construct(private readonly ShiftService $shifts)
    {
    }

    public function index(Request $request)
    {
        $query = OpsShift::with(['user:id,name,admin_role', 'orgUnit:id,name', 'city:id,name'])
            ->when($request->get('user_id'), fn ($q, $u) => $q->where('user_id', $u))
            ->when($request->get('org_unit_id'), fn ($q, $o) => $q->where('org_unit_id', $o))
            ->when($request->get('city_id'), fn ($q, $c) => $q->where('city_id', $c))
            ->when($request->get('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->get('date'), function ($q, $date) {
                $q->where('starts_at', '<', \Carbon\Carbon::parse($date)->endOfDay())
                    ->where('ends_at', '>', \Carbon\Carbon::parse($date)->startOfDay());
            })
            ->orderBy('starts_at');

        return response()->json($query->paginate((int) $request->get('per_page', 50)));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'org_unit_id' => 'sometimes|nullable|uuid|exists:org_units,id',
            'city_id' => 'sometimes|nullable|uuid|exists:cities,id',
            'role' => 'sometimes|nullable|string|max:40',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'notes' => 'sometimes|nullable|string|max:200',
        ]);

        $shift = $this->shifts->create($validated, $request->user());

        AuditLogService::log('shift.create', 'Ops', 'ops_shift', $shift->id, null, [
            'user_id' => $shift->user_id,
            'org_unit_id' => $shift->org_unit_id,
            'city_id' => $shift->city_id,
        ]);

        return response()->json(['shift' => $shift->load(['user:id,name', 'orgUnit:id,name', 'city:id,name'])], 201);
    }

    public function activate(Request $request, OpsShift $shift)
    {
        $shift = $this->shifts->activate($shift);
        AuditLogService::log('shift.activate', 'Ops', 'ops_shift', $shift->id);

        return response()->json(['shift' => $shift->load(['user:id,name', 'orgUnit:id,name', 'city:id,name'])]);
    }

    public function cancel(Request $request, OpsShift $shift)
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:200',
        ]);

        $shift = $this->shifts->cancel($shift, $validated['reason']);
        AuditLogService::log('shift.cancel', 'Ops', 'ops_shift', $shift->id, null, [
            'reason' => $validated['reason'],
        ]);

        return response()->json(['shift' => $shift->load(['user:id,name', 'orgUnit:id,name', 'city:id,name'])]);
    }

    public function rotate(Request $request, ShiftRotationPlanner $planner, RotationGapIncidentService $gapIncidents)
    {
        $validated = $request->validate([
            'week_start' => 'sometimes|date',
            'city_id' => 'sometimes|nullable|uuid|exists:cities,id',
            'org_unit_id' => 'sometimes|nullable|uuid|exists:org_units,id',
            'start_time' => 'sometimes|date_format:H:i',
            'hours' => 'sometimes|integer|min:1|max:12',
            'per_day' => 'sometimes|integer|min:1|max:20',
            'days' => 'sometimes|integer|min:1|max:7',
            'apply' => 'sometimes|boolean',
        ]);

        $weekStart = isset($validated['week_start'])
            ? \Carbon\Carbon::parse($validated['week_start'])
            : now()->startOfWeek(\Carbon\Carbon::MONDAY)->addWeek();

        $plan = $planner->plan($weekStart, [
            'city_id' => $validated['city_id'] ?? null,
            'org_unit_id' => $validated['org_unit_id'] ?? null,
            'start_time' => $validated['start_time'] ?? '09:00',
            'hours' => $validated['hours'] ?? 8,
            'per_day' => $validated['per_day'] ?? 1,
            'days' => $validated['days'] ?? 7,
        ]);

        $result = null;
        $incidents = null;
        if ($request->boolean('apply')) {
            $result = $planner->apply($plan, $request->user());
            $incidents = $gapIncidents->raise($plan, $request->user());

            AuditLogService::log('shift.rotate', 'Ops', 'ops_shift', null, null, [
                'week_start' => $plan['week_start'],
                'created' => $result['created'],
                'skipped' => $result['skipped'],
                'gap_incidents' => $incidents,
            ]);
        }

        return response()->json(['plan' => $plan, 'result' => $result, 'incidents' => $incidents]);
    }

    public function coverage(Request $request)
    {
        $validated = $request->validate([
            'city_id' => 'sometimes|nullable|uuid|exists:cities,id',
            'date' => 'sometimes|date',
        ]);

        return response()->json($this->shifts->coverage(
            $validated['city_id'] ?? null,
            $validated['date'] ?? now()->toDateString(),
        ));
    }
}
