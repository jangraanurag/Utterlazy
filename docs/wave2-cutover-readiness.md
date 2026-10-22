# 🚦 Wave 2 — Cutover readiness gate

A single hard gate that decides whether Wave 2 cutover flags are credible. It is **advisory to
the router** (traffic is not proxied yet) and exists to make blockers explicit instead of
discovering them during a flag flip.

## Checks (all blocking)

| Check | Blocks when |
|---|---|
| `cutover_flag` | `ORDER_COMMAND_CUTOVER_ENABLED` is false |
| `cutover_certified` | `ORDER_COMMAND_CUTOVER_READY` is false |
| `allowlist` | no city/restaurant is allowlisted |
| `pricing_samples` | 0 pricing parity checks in 24h |
| `pricing_mismatch` | any mismatch in 24h |
| `java_health` | `/api/orders/health` unreachable (2s timeout) |
| `service_token` | `SERVICE_TOKEN` missing |
| `certification_artifact` | latest report older than 7 days or contains FAIL (optional unless `CUTOVER_REQUIRE_CERTIFICATION=true`) |

## API / command / UI

```bash
php artisan cutover:check            # returns non-zero if flags are on and blocked
php artisan cutover:check --strict   # non-zero on any blocker
curl /api/admin/cutover/readiness    # same payload (admin.auth:command_center.view)
```

Response: `allowed`, `checks[]`, `blockers[]`, `flags`, `checked_at`. Cached 30s.
Command Center shows a **Cutover gate: READY/BLOCKED** card with blocker count.

## Flag semantics (unchanged)

```bash
ORDER_COMMAND_CUTOVER_ENABLED=true
ORDER_COMMAND_CUTOVER_READY=true        # only after certification
ORDER_COMMAND_CITIES=<uuid,...>          # and/or ORDER_COMMAND_RESTAURANTS
ORDER_COMMAND_SHADOW_ENABLED=true        # measurement
```

Even when all checks pass, **traffic still stays on Laravel**: checkout is not wired to proxy
to Java. Wiring requires Java ownership of pricing, payments, notifications, and rider
assignment — tracked separately. Flipping flags alone cannot misroute orders.

## Certification artifact

`infra/certification/report-*.json` is produced by `scripts/certify.sh`. Set
`CERTIFICATION_DIR` if reports live elsewhere, and `CUTOVER_REQUIRE_CERTIFICATION=true` to make
a missing/stale artifact blocking.

## Tests

9 checks in `tests/wave1_smoke.php`: full-evidence pass, flag block, mismatch, no samples,
Java down, stale artifact, failed artifact, empty allowlist, and check count.
