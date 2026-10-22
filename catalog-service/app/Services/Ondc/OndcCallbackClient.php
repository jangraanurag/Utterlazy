<?php

namespace App\Services\Ondc;

use Illuminate\Support\Facades\Http;

/**
 * Sends signed Beckn callbacks to a BAP.
 *
 * Transport is injectable for tests. Retries live in the queued job, not here.
 */
class OndcCallbackClient
{
    /** @var callable(string,string,array<string,string>,string):array{ok:bool,status:int} */
    private $transport;

    public function __construct(private readonly OndcSigner $signer, ?callable $transport = null)
    {
        $this->transport = $transport ?? function (string $method, string $url, array $headers, string $body): array {
            $response = Http::timeout(5)
                ->withHeaders($headers)
                ->withBody($body, 'application/json')
                ->send($method, $url);

            return ['ok' => $response->successful(), 'status' => $response->status()];
        };
    }

    public static function fromConfig(): ?self
    {
        if (! config('services.ondc.callback_enabled', false)) {
            return null;
        }

        $signer = OndcSigner::fromConfig();

        return $signer ? new self($signer) : null;
    }

    /** @return array{ok:bool, status:int} */
    public function send(array $payload, string $bapUri): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $authorization = $this->signer->sign($body);

        return ($this->transport)('POST', $bapUri, [
            'Authorization' => $authorization,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $body);
    }
}
