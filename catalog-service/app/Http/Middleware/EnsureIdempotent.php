<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotent
{
    /**
     * Prevent duplicate requests using an idempotency key.
     * Client sends `Idempotency-Key` (or `X-Idempotency-Key`) header.
     *
     * Storage is the `idempotency_keys` DB table (24h TTL via
     * `idempotency:prune`), not cache: rows survive cache flushes and are
     * written inside the request lifecycle. WhatsApp checkout additionally
     * enforces the key inside the DB transaction for race safety.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key') ?? $request->header('X-Idempotency-Key');

        if (! $key) {
            return $next($request);
        }

        $userId = $request->user()?->id;
        if (! $userId) {
            return $next($request);
        }

        $cached = DB::table('idempotency_keys')
            ->where('key', $key)
            ->where('user_id', $userId)
            ->first();

        if ($cached) {
            $stored = json_decode($cached->response, true);

            if (($stored['pending'] ?? false) !== true) {
                return response()->json($stored['body'] ?? [], $stored['code'] ?? 200);
            }
        }

        $response = $next($request);

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            DB::table('idempotency_keys')->updateOrInsert(
                ['key' => $key, 'user_id' => $userId],
                [
                    'response' => json_encode([
                        'code' => $response->getStatusCode(),
                        'body' => json_decode($response->getContent(), true),
                    ]),
                    'created_at' => now(),
                ],
            );
        }

        return $response;
    }
}
