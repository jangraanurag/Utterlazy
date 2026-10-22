<?php

namespace App\Services\Analytics;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ClickHouseQueryClient implements ClickHouseQuery
{
    private string $baseUrl;

    private ?string $user;

    private ?string $password;

    public function __construct(?string $baseUrl = null, ?string $user = null, ?string $password = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? (string) config('services.analytics.clickhouse_url', 'http://clickhouse:8123'), '/');
        $this->user = $user ?? config('services.analytics.clickhouse_user');
        $this->password = $password ?? config('services.analytics.clickhouse_password');
    }

    public function rows(string $sql): array
    {
        $request = Http::timeout(5)->withQueryParameters([
            'query' => rtrim($sql, ';').' FORMAT JSON',
        ]);

        if ($this->user) {
            $request = $request->withBasicAuth($this->user, (string) $this->password);
        }

        $response = $request->get($this->baseUrl.'/');

        if ($response->failed()) {
            throw new RuntimeException('ClickHouse query failed: HTTP '.$response->status());
        }

        $decoded = $response->json();

        if (! is_array($decoded) || ! isset($decoded['data']) || ! is_array($decoded['data'])) {
            throw new RuntimeException('ClickHouse returned an unexpected payload.');
        }

        return $decoded['data'];
    }
}
