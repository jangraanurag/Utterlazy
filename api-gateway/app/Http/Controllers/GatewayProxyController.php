<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Http;

class GatewayProxyController extends Controller
{
    /**
     * Forward static assets (images, files) to the catalog service WITHOUT
     * the /api prefix — CATALOG_SERVICE_URL ends in /api which is correct
     * for API routes but breaks public asset paths.
     */
    public function forwardAsset(Request $request, string $path = '')
    {
        $baseUrl = rtrim(env('CATALOG_SERVICE_URL'), '/');
        if (str_ends_with($baseUrl, '/api')) {
            $baseUrl = substr($baseUrl, 0, -4);
        }
        // Route is /images/{path} — the path excludes the images/ prefix.
        $url = $baseUrl . '/images/' . $path;

        $response = Http::withHeaders([
            'Accept' => $request->header('Accept', '*/*'),
        ])->send($request->method(), $url, ['query' => $request->query()]);

        $headers = collect($response->headers())
            ->except(['Transfer-Encoding', 'Connection', 'Keep-Alive'])
            ->map(fn ($values) => $values[0] ?? '')
            ->all();

        return response($response->body(), $response->status())
            ->withHeaders($headers);
    }

    public function forwardToCatalog(Request $request, $path = '')
    {
        $baseUrl = env('CATALOG_SERVICE_URL');
        $url = $baseUrl . '/' . $path;

        $jsonBody = $request->json()->all();
        $options = [
            'query' => $request->query(),
        ];
        if (!empty($jsonBody)) {
            $options['json'] = $jsonBody;
        }

        $forwardHeaders = [
            'Accept' => 'application/json',
            'X-Vendor-ID' => $request->attributes->get('vendor_id', ''),
            'X-User-ID' => $request->user() ? $request->user()->id : '',
        ];

        if ($request->hasHeader('Authorization')) {
            $forwardHeaders['Authorization'] = $request->header('Authorization');
        }
        if ($request->hasHeader('Accept-Language')) {
            $forwardHeaders['Accept-Language'] = $request->header('Accept-Language');
        }

        $response = Http::withHeaders($forwardHeaders)
            ->send($request->method(), $url, $options);

        $headers = collect($response->headers())
            ->except(['Transfer-Encoding', 'Connection', 'Keep-Alive'])
            ->map(fn ($values) => $values[0] ?? '')
            ->all();

        return response($response->body(), $response->status())
            ->withHeaders($headers);
    }
}
