<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    public function check()
    {
        $checks = [
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'version' => config('app.version', '1.0.0'),
            'services' => [],
        ];

        // Database check
        try {
            DB::connection()->getPdo();
            $checks['services']['database'] = ['status' => 'up'];
        } catch (\Exception $e) {
            $checks['services']['database'] = ['status' => 'down', 'error' => $e->getMessage()];
            $checks['status'] = 'degraded';
        }

        // Redis check
        try {
            Redis::ping();
            $checks['services']['redis'] = ['status' => 'up'];
        } catch (\Exception $e) {
            $checks['services']['redis'] = ['status' => 'down', 'error' => $e->getMessage()];
            $checks['status'] = 'degraded';
        }

        // Cache check
        try {
            Cache::put('health_check', 'ok', 10);
            $cached = Cache::get('health_check');
            $checks['services']['cache'] = $cached === 'ok' ? ['status' => 'up'] : ['status' => 'degraded'];
        } catch (\Exception $e) {
            $checks['services']['cache'] = ['status' => 'down', 'error' => $e->getMessage()];
            $checks['status'] = 'degraded';
        }

        $statusCode = $checks['status'] === 'healthy' ? 200 : 503;

        return response()->json($checks, $statusCode);
    }

    public function ready()
    {
        return response()->json(['status' => 'ready']);
    }
}
