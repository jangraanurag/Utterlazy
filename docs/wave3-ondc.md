# 🔗 Wave 3 — ONDC seller adapter (protocol core)

Signed Beckn search in, `on_search` catalog out. **Order creation is intentionally disabled**:
`confirm` returns `501 ONDC_CONFIRM_NOT_ENABLED` until payment, fulfilment, and cancellation
parity are certified.

## Enabled surface

| Method | Path | Behaviour |
|---|---|---|
| POST | `/api/ondc/search` | verifies Ed25519 signature, returns `on_search`, queues a signed callback (when enabled) |
| POST | `/api/ondc/confirm` | always NACK while disabled |

Disabled by default (`ONDC_ENABLED=false` → `503 ONDC_DISABLED`).

## Signature (Beckn)

```
Authorization: Signature keyId="<subscriber>|<key>|ed25519",algorithm="ed25519",
               created="<unix>",expires="<unix>",signature="<base64>"
signing string = created|expires|base64(sha256(raw_body))
```

Verification: registry key lookup, Ed25519 verify, ±300s clock skew, expiry window,
malformed header rejection. Failures are logged with the transaction id and return
`401 INVALID_SIGNATURE`.

## Catalog mapping

`OndcCatalogMapper` produces `on_search` with:

- `context.action = on_search`, `bpp_id`, `bpp_uri`, timestamp
- `message.catalog["bpp/descriptor"]`, `bpp/providers[]`
- provider: id, descriptor, `locations[].gps`, items (id, name, INR price, category, fulfillment)
- `bpp/fulfillments` = Delivery

Search intent (`message.intent.item.descriptor.name` or `category.id`) filters restaurants by
name/cuisine. Max 10 providers × 20 items per response.

> The integration harness response includes the payload inline as `on_search`. Production must
> POST the same payload to `bap_uri` with an outbound signature (transport pending).

## Config

```bash
ONDC_ENABLED=false
ONDC_REQUIRE_SIGNATURE=true
ONDC_SUBSCRIBER_ID=seller.example
ONDC_KEY_ID=key-1
ONDC_PRIVATE_KEY=<base64 ed25519 secret>
ONDC_BPP_ID=seller.example
ONDC_BPP_URI=https://seller.example/ondc
ONDC_REGISTRY_KEYS={"seller.example|key-1|ed25519":"<base64 public key>"}
ONDC_CALLBACK_ENABLED=false   # queue signed on_search to bap_uri
```

## Outbound callbacks (built)

When `ONDC_CALLBACK_ENABLED=true`, search queues `SendOndcCallback` (3 tries, 5s backoff) which
POSTs the `on_search` payload to `context.bap_uri` with a fresh Ed25519 `Authorization` header.

- Transport is injectable; tests verify URL, content type, signature, and failure surfacing.
- The inline `on_search` is still returned for the integration harness.
- Order lifecycle (`select/init/confirm`, payments, RTO) remains disabled.

## Test coverage

14 checks in `tests/wave1_smoke.php`: sign/verify round trip, tampered body, unknown key,
expired window, malformed header, provider/gps/price/fulfilment mapping, and `on_search`
context/action/bpp fields.

## Explicitly not done

- `select` / `init` / `confirm` order lifecycle
- ONDC payment, cancellation, and RTO flows
- registry onboarding/subscription handshake

Each of these needs its own parity and failure-mode review before enablement.
