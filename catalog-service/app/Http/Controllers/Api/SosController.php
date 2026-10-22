<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SosAlert;
use App\Services\AuditLogService;
use App\Services\Sos\SosService;
use Illuminate\Http\Request;

class SosController extends Controller
{
    public function __construct(private readonly SosService $sos)
    {
    }

    /**
     * Rider-triggered SOS. Idempotent for 5 minutes to survive button mashing.
     */
    public function trigger(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'sometimes|nullable|uuid|exists:orders,id',
            'latitude' => 'sometimes|nullable|numeric|between:-90,90',
            'longitude' => 'sometimes|nullable|numeric|between:-180,180',
            'note' => 'sometimes|nullable|string|max:200',
            'battery' => 'sometimes|nullable|integer|min:0|max:100',
        ]);

        $result = $this->sos->trigger($request->user(), $validated);

        return response()->json([
            'alert' => $result['alert'],
            'deduplicated' => $result['deduplicated'],
            'incident_id' => $result['alert']->incident_id,
            'message' => $result['deduplicated']
                ? 'An SOS is already open. Help is on the way.'
                : 'SOS received. Rider ops and safety partners have been alerted.',
        ], $result['deduplicated'] ? 200 : 201);
    }

    public function index(Request $request)
    {
        $query = SosAlert::with(['rider:id,name,phone', 'order:id,status'])
            ->when($request->get('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->boolean('open_only'), fn ($q) => $q->whereIn('status', ['open', 'ack']))
            ->orderByDesc('triggered_at');

        return response()->json([
            'summary' => [
                'open' => SosAlert::where('status', 'open')->count(),
                'ack' => SosAlert::where('status', 'ack')->count(),
                'resolved_24h' => SosAlert::where('status', 'resolved')
                    ->where('resolved_at', '>=', now()->subDay())->count(),
            ],
            'alerts' => $query->paginate((int) $request->get('per_page', 25)),
        ]);
    }

    public function acknowledge(Request $request, SosAlert $sos)
    {
        $alert = $this->sos->acknowledge($sos, $request->user());
        AuditLogService::log('sos.ack', 'Safety', 'sos_alert', $alert->id);

        return response()->json(['alert' => $alert->load('rider:id,name,phone')]);
    }

    public function resolve(Request $request, SosAlert $sos)
    {
        $validated = $request->validate([
            'note' => 'required|string|min:10|max:500',
        ]);

        $alert = $this->sos->resolve($sos, $request->user(), $validated['note']);
        AuditLogService::log('sos.resolve', 'Safety', 'sos_alert', $alert->id, null, [
            'note' => $validated['note'],
        ]);

        return response()->json(['alert' => $alert->load('rider:id,name,phone')]);
    }
}
