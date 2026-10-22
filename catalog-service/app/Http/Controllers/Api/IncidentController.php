<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OpsIncident;
use App\Services\AuditLogService;
use App\Services\IncidentService;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    public function __construct(private readonly IncidentService $incidents)
    {
    }

    public function index(Request $request)
    {
        $query = OpsIncident::with(['owner:id,name', 'city:id,name'])
            ->when($request->get('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->get('severity'), fn ($q, $s) => $q->where('severity', $s))
            ->when($request->get('city_id'), fn ($q, $c) => $q->where('city_id', $c))
            ->when($request->boolean('open_only'), fn ($q) => $q->whereIn('status', ['open', 'ack']))
            ->orderByRaw("FIELD(severity, 'sev1', 'sev2', 'sev3')")
            ->orderByDesc('opened_at');

        $summary = [
            'open' => OpsIncident::whereIn('status', ['open', 'ack'])->count(),
            'sev1_open' => OpsIncident::whereIn('status', ['open', 'ack'])->where('severity', 'sev1')->count(),
            'breached_open' => OpsIncident::whereIn('status', ['open', 'ack'])->whereNotNull('sla_breached_at')->count(),
            'resolved_24h' => OpsIncident::where('status', 'resolved')
                ->where('resolved_at', '>=', now()->subDay())->count(),
        ];

        return response()->json([
            'summary' => $summary,
            'incidents' => $query->paginate((int) $request->get('per_page', 25)),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'city_id' => 'sometimes|nullable|uuid|exists:cities,id',
            'severity' => 'required|in:sev1,sev2,sev3',
            'category' => 'required|in:payments,riders,kitchen,whatsapp,infra,catalog,safety,staffing,other',
            'title' => 'required|string|min:5|max:160',
            'description' => 'sometimes|nullable|string|max:2000',
            'owner_user_id' => 'sometimes|nullable|uuid|exists:users,id',
        ]);

        $incident = $this->incidents->create($validated, $request->user());

        AuditLogService::log('incident.create', 'Ops', 'ops_incident', $incident->id, null, [
            'severity' => $incident->severity,
            'category' => $incident->category,
            'city_id' => $incident->city_id,
        ]);

        return response()->json(['incident' => $incident->load(['owner:id,name', 'city:id,name'])], 201);
    }

    public function acknowledge(Request $request, OpsIncident $incident)
    {
        $incident = $this->incidents->acknowledge($incident, $request->user());
        AuditLogService::log('incident.ack', 'Ops', 'ops_incident', $incident->id);

        return response()->json(['incident' => $incident->load(['owner:id,name', 'city:id,name'])]);
    }

    public function resolve(Request $request, OpsIncident $incident)
    {
        $validated = $request->validate([
            'note' => 'required|string|min:10|max:500',
        ]);

        $incident = $this->incidents->resolve($incident, $request->user(), $validated['note']);
        AuditLogService::log('incident.resolve', 'Ops', 'ops_incident', $incident->id, null, [
            'note' => $validated['note'],
        ]);

        return response()->json(['incident' => $incident->load(['owner:id,name', 'city:id,name'])]);
    }

    public function assign(Request $request, OpsIncident $incident)
    {
        $validated = $request->validate([
            'owner_user_id' => 'required|uuid|exists:users,id',
        ]);

        $incident = $this->incidents->assign($incident, $validated['owner_user_id'], $request->user());
        AuditLogService::log('incident.assign', 'Ops', 'ops_incident', $incident->id, null, [
            'owner_user_id' => $validated['owner_user_id'],
        ]);

        return response()->json(['incident' => $incident->load(['owner:id,name', 'city:id,name'])]);
    }
}
