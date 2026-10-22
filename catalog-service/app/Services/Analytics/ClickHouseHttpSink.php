<?php

namespace App\Services\Analytics;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ClickHouseHttpSink implements AnalyticsSink
{
    private string $baseUrl;

    private ?string $user;

    private ?string $password;

    private string $database;

    public function __construct(
        ?string $baseUrl = null,
        ?string $user = null,
        ?string $password = null,
        ?string $database = null,
    ) {
        $this->baseUrl = rtrim($baseUrl ?? (string) config('services.analytics.clickhouse_url', 'http://clickhouse:8123'), '/');
        $this->user = $user ?? config('services.analytics.clickhouse_user');
        $this->password = $password ?? config('services.analytics.clickhouse_password');
        $this->database = $database ?? (string) config('services.analytics.database', 'utterlazy');
    }

    public function insert(string $table, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $body = '';
        foreach ($rows as $row) {
            $body .= json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
        }

        $query = "INSERT INTO {$this->database}.{$table} FORMAT JSONEachRow";

        $request = Http::timeout(10)
            ->withHeaders(['Content-Type' => 'application/x-ndjson'])
            ->withQueryParameters(['query' => $query]);

        if ($this->user) {
            $request = $request->withBasicAuth($this->user, (string) $this->password);
        }

        // send() keeps the raw NDJSON body; post() would inject an empty JSON body.
        $response = $request->withBody($body, 'application/x-ndjson')->send('POST', $this->baseUrl.'/');

        if ($response->failed()) {
            throw new RuntimeException(
                "ClickHouse insert failed for {$table}: HTTP {$response->status()} ".mb_substr($response->body(), 0, 300)
            );
        }
    }
}
