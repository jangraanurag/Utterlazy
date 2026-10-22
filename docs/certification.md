# ✅ Production certification

One command proves every automated gate. Human sign-off items are listed separately.

```bash
# local / CI (no infrastructure needed)
MODE=offline WITH_FRONTENDS=true RANKER_DEPS=/path/to/deps ./scripts/certify.sh

# staging (run on the deploy host with docker compose)
MODE=staging \
SERVICE_TOKEN=... CITY_ID=<city-uuid> COMPOSE_FILE=docker-compose.prod.yml \
ORDER_COMMAND_SHADOW_ENABLED=true RANKER_URL=http://127.0.0.1:8010 \
./scripts/certify.sh
```

Artifacts: `infra/certification/report-<utc>.md` + `.json` (gitignored).
Exit code is non-zero on any FAIL. **SKIP is not a pass.**

## Automated gates — offline

| Gate | Proves |
|---|---|
| PHP wave1 smoke (325 checks) | outbox, idempotency, placements, reversal, refund retry, cities, analytics, incidents, shifts, SOS, ONDC, OTP, dataset features |
| WhatsApp helper tests + boot | phone normalization, state wiring, routes, boot |
| Pricing parity (PHP → Java) | 10 golden vectors, persisted money fields identical |
| Order-command core self-test | state machine, idempotency, pricing snapshot, transitions |
| Spring adapter tests | REST, JDBC repo, optimistic locking, outbox, preflight (H2) |
| Ranker tests | ranking policy, sponsored rejection, trainer, model gate |
| Compose + CI YAML | all compose files and the CI workflow parse |
| Docs present | 16+ architecture/HLD/LLD/runbook/wave documents |
| Frontend builds | admin + vendor production builds |

## Automated gates — staging

| Gate | Proves |
|---|---|
| `wave1_verify.sh` | health, schema, Kafka topics, outbox lag, paid-only reco, refund integrity, consumer dedupe |
| `pricing:parity-report --hours=24` | real-order Java vs Laravel pricing (exit non-zero on mismatch) |
| `order-command:compare --hours=24 --fail-on-mismatch` | shadow order parity (status/channel/total/lines/money) |
| Ranker health | service reachable |

## Human sign-off (not automatable)

- [ ] 48h soak: zero duplicate orders, DLQ empty, `refund_pending` older than 1h = 0
- [ ] Meta utility/marketing templates approved; opt-in language reviewed
- [ ] Java→Laravel projection running with Kafka in production; `projection:status` non-zero
- [ ] 7-day 100% pricing match + `php artisan cutover:check` all-PASS before `ORDER_COMMAND_CUTOVER_READY=true`
- [ ] Ranker shadow week → ramp 10/25/50 with guardrails
- [ ] SOS/insurance partner contract signed; webhook tested with the partner
- [ ] ONDC registry onboarding + signed callback transport reviewed (confirm stays NACKed)
- [ ] PII retention/legal review; export-log sampling by auditor
- [ ] Shift coverage policy + on-call rota agreed by support leadership
- [ ] Rollback drills executed (Wave 1 services stop; ranker kill switch; cutover flags off)

## CI mapping

`.github/workflows/wave1-gates.yml` runs the same class of gates on every PR:

- `php-lint-and-smoke` — PHP lint + 325-check smoke + WhatsApp boot/tests
- `order-command` — core self-test + Spring adapter tests
- `pricing-parity` — PHP golden vectors → Java
- `ranker-tests` — core + API + trainer
- `frontend-build` — admin + vendor
- `compose-validate` — both compose files

Certification adds the report artifact and the staging gates; it does not replace CI.
