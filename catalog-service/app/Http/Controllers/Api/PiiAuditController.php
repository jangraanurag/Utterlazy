<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PiiAccessLog;
use App\Models\PiiRetentionRun;
use Illuminate\Http\Request;

class PiiAuditController extends Controller
{
    public function retentionRuns()
    {
        return response()->json([
            'runs' => PiiRetentionRun::with('actor:id,name,email')
                ->orderByDesc('created_at')
                ->limit(50)
                ->get(),
        ]);
    }

    /**
     * Auditor view of PII views/exports. Read-only by design.
     */
    public function index(Request $request)
    {
        $query = PiiAccessLog::with('actor:id,name,admin_role')
            ->when($request->get('actor_user_id'), fn ($q, $a) => $q->where('actor_user_id', $a))
            ->when($request->get('action'), fn ($q, $a) => $q->where('action', $a))
            ->when($request->get('resource_type'), fn ($q, $r) => $q->where('resource_type', $r))
            ->when($request->get('from'), fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->when($request->get('to'), fn ($q, $d) => $q->where('created_at', '<=', $d))
            ->orderByDesc('created_at');

        $since = now()->subDay();

        $summary = [
            'last_24h' => PiiAccessLog::where('created_at', '>=', $since)->count(),
            'exports_24h' => PiiAccessLog::where('created_at', '>=', $since)->where('action', 'export')->count(),
            'views_24h' => PiiAccessLog::where('created_at', '>=', $since)->where('action', 'view')->count(),
            'actors_24h' => PiiAccessLog::where('created_at', '>=', $since)->distinct('actor_user_id')->count('actor_user_id'),
        ];

        return response()->json([
            'summary' => $summary,
            'logs' => $query->paginate((int) $request->get('per_page', 50)),
        ]);
    }
}
