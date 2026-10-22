<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PricingParityCheck;
use Illuminate\Http\Request;

class PricingParityController extends Controller
{
    /**
     * Cutover certification summary: how often the Java pricing engine agrees
     * with what Laravel charged on real orders.
     */
    public function summary(Request $request)
    {
        $hours = (int) $request->get('hours', 24);
        $base = PricingParityCheck::where('created_at', '>=', now()->subHours($hours));

        $total = (clone $base)->count();
        $matched = (clone $base)->where('matched', true)->count();
        $mismatched = $total - $matched;

        $latest = (clone $base)->where('matched', false)
            ->orderByDesc('created_at')
            ->limit((int) $request->get('limit', 20))
            ->get(['order_id', 'city_id', 'restaurant_id', 'diffs', 'created_at']);

        return response()->json([
            'window_hours' => $hours,
            'total' => $total,
            'matched' => $matched,
            'mismatched' => $mismatched,
            'mismatch_rate_percent' => $total > 0 ? round(($mismatched / $total) * 100, 3) : 0.0,
            'cutover_ready' => (bool) config('services.order_command.cutover_ready'),
            'latest_mismatches' => $latest,
        ]);
    }
}
