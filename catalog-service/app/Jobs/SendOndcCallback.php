<?php

namespace App\Jobs;

use App\Services\Ondc\OndcCallbackClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SendOndcCallback implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(public array $payload, public string $bapUri)
    {
    }

    public function handle(): void
    {
        $client = OndcCallbackClient::fromConfig();

        if (! $client) {
            return; // callback disabled or signer not configured
        }

        $result = $client->send($this->payload, $this->bapUri);

        if (! $result['ok']) {
            throw new RuntimeException('ONDC callback failed with HTTP '.$result['status']);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ONDC callback permanently failed.', [
            'bap_uri' => $this->bapUri,
            'transaction_id' => $this->payload['context']['transaction_id'] ?? null,
            'error' => $e->getMessage(),
        ]);
    }
}
