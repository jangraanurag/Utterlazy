<?php

namespace App\Services\Ondc;

/**
 * Verifies inbound Beckn Authorization headers.
 *
 * @param array<string, string> $registryKeys keyId => base64 Ed25519 public key
 */
class OndcVerifier
{
    private const PUBLIC_KEY_BYTES = SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES;

    public function __construct(
        private readonly array $registryKeys,
        private readonly int $clockSkewSeconds = 300,
    ) {
    }

    /** @return array{ok:bool, error?:string, key_id?:string} */
    public function verify(string $authorization, string $body, ?int $now = null): array
    {
        $now ??= time();
        $parts = $this->parse($authorization);

        if ($parts === null) {
            return ['ok' => false, 'error' => 'malformed_authorization'];
        }

        $created = (int) $parts['created'];
        $expires = (int) $parts['expires'];

        if ($expires <= $created) {
            return ['ok' => false, 'error' => 'invalid_window'];
        }

        if ($now < $created - $this->clockSkewSeconds || $now > $expires + $this->clockSkewSeconds) {
            return ['ok' => false, 'error' => 'expired_or_not_yet_valid'];
        }

        $keyId = $parts['keyId'];
        $publicKey = $this->registryKeys[$keyId] ?? null;

        if (! $publicKey) {
            return ['ok' => false, 'error' => 'unknown_key', 'key_id' => $keyId];
        }

        $publicKeyBytes = base64_decode($publicKey, true);
        if ($publicKeyBytes === false || strlen($publicKeyBytes) !== self::PUBLIC_KEY_BYTES) {
            return ['ok' => false, 'error' => 'invalid_registry_key', 'key_id' => $keyId];
        }

        $signature = base64_decode($parts['signature'], true);
        if ($signature === false) {
            return ['ok' => false, 'error' => 'invalid_signature_encoding'];
        }

        $signingString = $created.'|'.$expires.'|'.base64_encode(hash('sha256', $body, true));

        if (! sodium_crypto_sign_verify_detached($signature, $signingString, $publicKeyBytes)) {
            return ['ok' => false, 'error' => 'invalid_signature', 'key_id' => $keyId];
        }

        return ['ok' => true, 'key_id' => $keyId];
    }

    /** @return array<string, string>|null */
    private function parse(string $header): ?array
    {
        if (! str_starts_with(trim($header), 'Signature ')) {
            return null;
        }

        preg_match_all('/(\w+)="([^"]*)"/', $header, $matches, PREG_SET_ORDER);

        $out = [];
        foreach ($matches as $match) {
            $out[$match[1]] = $match[2];
        }

        foreach (['keyId', 'algorithm', 'created', 'expires', 'signature'] as $required) {
            if (! isset($out[$required])) {
                return null;
            }
        }

        if (strtolower($out['algorithm']) !== 'ed25519') {
            return null;
        }

        return $out;
    }
}
