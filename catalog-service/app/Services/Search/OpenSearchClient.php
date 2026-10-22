<?php

namespace App\Services\Search;

use Illuminate\Support\Facades\Http;

/**
 * Minimal OpenSearch/Elasticsearch HTTP client (no external PHP dependency).
 * Transport is injectable for tests.
 */
class OpenSearchClient
{
    /** @var callable(string,string,array<string,string>,string):array{ok:bool,status:int,body?:string} */
    private $transport;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $index,
        ?callable $transport = null,
    ) {
        $this->transport = $transport ?? function (string $method, string $url, array $headers, string $body): array {
            $response = Http::timeout(5)->withHeaders($headers)->withBody($body, 'application/json')->send($method, $url);

            return ['ok' => $response->successful(), 'status' => $response->status(), 'body' => $response->body()];
        };
    }

    public static function fromConfig(): ?self
    {
        if (! config('services.opensearch.enabled', false)) {
            return null;
        }

        return new self(
            rtrim((string) config('services.opensearch.url', 'http://opensearch:9200'), '/'),
            (string) config('services.opensearch.index', 'utterlazy_restaurants'),
        );
    }

    public function search(array $body): ?array
    {
        $result = ($this->transport)(
            'POST',
            $this->baseUrl.'/'.$this->index.'/_search',
            ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            json_encode($body, JSON_UNESCAPED_SLASHES),
        );

        if (! $result['ok']) {
            return null;
        }

        $decoded = json_decode((string) ($result['body'] ?? ''), true);

        return is_array($decoded) ? $decoded : null;
    }

    public function bulk(array $documents): bool
    {
        if ($documents === []) {
            return true;
        }

        $lines = '';
        foreach ($documents as $doc) {
            $id = $doc['id'] ?? null;
            if (! $id) {
                continue;
            }

            $lines .= json_encode(['index' => ['_index' => $this->index, '_id' => $id]], JSON_UNESCAPED_SLASHES)."\n";
            $lines .= json_encode($doc, JSON_UNESCAPED_SLASHES)."\n";
        }

        $result = ($this->transport)(
            'POST',
            $this->baseUrl.'/_bulk',
            ['Content-Type' => 'application/x-ndjson', 'Accept' => 'application/json'],
            $lines,
        );

        return (bool) $result['ok'];
    }
}
