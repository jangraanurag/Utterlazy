<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class ServiceAuth
{
    /**
     * Service-to-service auth for internal endpoints (e.g. whatsapp-service).
     * Shared secret configured as SERVICE_TOKEN on both sides.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.internal_token');
        $provided = $request->header('X-Service-Token');

        if (! $expected || ! $provided || ! hash_equals($expected, $provided)) {
            return response()->json(['error' => 'Service token required.'], 401);
        }

        return $next($request);
    }
}
