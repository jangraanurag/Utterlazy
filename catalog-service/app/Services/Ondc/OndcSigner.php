<?php

namespace App\Services\Ondc;

use RuntimeException;

/**
 * Beckn/ONDC Ed25519 request signing.
 *
 * Authorization: Signature keyId="subscriber|key|ed25519",algorithm="ed25519",
 * created="<unix>",expires="<unix>",signature="<base64>"
 * Signing string: created|expires|base64(sha256(body))
 */
class OndcSigner
{
    public function __construct(
        private readonly string $subscriberId,
        private readonly string $keyId,
        private readonly string $privateKeyBase64,
    ) {
    }

    public static function fromConfig(): ?self
    {
        $subscriber = (string) config('services.ondc.subscriber_id', '');
        $keyId = (string) config('services.ondc.key_id', '');
        $privateKey = (string) config('services.ondc.private_key', '');

        if ($subscriber === '' || $keyId === '' || $privateKey === '') {
            return null;
        }

        return new self($subscriber, $keyId, $privateKey);
    }

    public function sign(string $body, ?int $created = null, int $ttlSeconds = 300): string
    {
        $created ??= time();
        $expires = $created + $ttlSeconds;
        $signing = $this->signingString($body, $created, $expires);
        $secret = base64_decode($this->privateKeyBase64, true);

        if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new RuntimeException('Invalid ONDC private key.');
        }

        $signature = sodium_crypto_sign_detached($signing, $secret);

        return sprintf(
            'Signature keyId="%s|%s|ed25519",algorithm="ed25519",created="%d",expires="%d",signature="%s"',
            $this->subscriberId,
            $this->keyId,
            $created,
            $expires,
            base64_encode($signature),
        );
    }

    public function signingString(string $body, int $created, int $expires): string
    {
        return $created.'|'.$expires.'|'.base64_encode(hash('sha256', $body, true));
    }
}
