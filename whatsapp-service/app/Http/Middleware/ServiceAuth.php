<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ServiceAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('whatsapp.service_token');
        $provided = $request->header('X-Service-Token');

        if (! $expected || ! $provided || ! hash_equals($expected, $provided)) {
            return response()->json(['error' => 'Service token required.'], 401);
        }

        return $next($request);
    }
}
