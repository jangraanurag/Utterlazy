<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FraudLog;
use Illuminate\Http\Request;

class FraudController extends Controller
{
    public function index(Request $request)
    {
        $logs = FraudLog::latest()->paginate(20);

        return response()->json($logs);
    }

    public function store(Request $request)
    {
        $validated = $request->validate(['type' => 'required|string', 'entity_type' => 'required|string', 'entity_id' => 'required|string', 'description' => 'required|string', 'severity' => 'sometimes|in:low,medium,high,critical']);
        $log = FraudLog::create($validated);

        return response()->json(['message' => 'Fraud logged', 'log' => $log], 201);
    }

    public function review(Request $request, FraudLog $log)
    {
        $log->update(['status' => 'reviewed']);

        return response()->json(['message' => 'Reviewed', 'log' => $log->fresh()]);
    }
}
