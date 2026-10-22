# 🧱 Wave 2 — order-command extraction (contract + status)

The Java core exists and is tested. **It is not in the write path.** `catalog-service`
remains the owner until the gate in `docs/deploy-runbook.md` §6 is met and shadow parity
is proven.

## Progress

| Step | Status |
|---|---|
| Framework-free domain core (state machine, place-order, idempotency) | ✅ built |
| Self-test (23 checks, `./scripts/order_core_test.sh`) | ✅ passing |
| Transition service writes state + events in one unit of work | ✅ built |
| Spring adapter (REST + JDBC repo + Kafka outbox) | ✅ built |
| Adapter integration tests (MockMvc + H2, 8 tests) | ✅ passing |
| Shadow-mode compose profile (`wave2`, outbox disabled) | ✅ added |
| Pricing parity contract (PHP golden vectors → Java calculator) | ✅ 10/10 |
| Pricing breakdown persisted on shadow orders (grand total, tax, discount, fees, commission, payout) | ✅ |
| Shadow comparator compares money fields, not just totals | ✅ |
| Production pricing preflight + certification table/card | ✅ |
| 7-day 100% match certification | ⬜ staging/prod |
| Shadow mirror job (order create + every status change, after commit) | ✅ built |
| Parity comparator (`php artisan order-command:compare`) | ✅ built |
| Shadow idempotency key `shadow-<laravel_order_id>` | ✅ built |
| Geography model: `orders.city_id` + `restaurants.city_id` + safe backfill | ✅ built |
| Per-city order metrics in Command Center | ✅ built |
| Cutover router (`city` / `restaurant` allowlists, fail-closed) | ✅ built, not wired |
| Shadow mode: same request → Laravel and Java, compare responses + events | ⬜ run on staging |
| Cutover per city (`city_id` flag), Laravel checkout becomes proxy | ⬜ |
| Orders DB split by `city_id`; dispatch service | ⬜ |

## Invariants the adapter must preserve

1. Statuses are exactly: `pending → confirmed → preparing → ready_for_pickup →
   out_for_delivery → delivered`, side paths `cancelled`, `abandoned`, `rescued`.
2. `channel` is `app | web | whatsapp`; WhatsApp requires `Idempotency-Key`
   (8–80 chars) and only `prepaid | cod`.
3. One order + its events commit in a single transaction (outbox row per event).
4. Idempotent replay returns the original order; the unique key is
   `(user_id, idempotency_key)`.
5. Amounts are `BigDecimal` with 2 dp; never floats.
6. Events use the existing outbox envelope: `event_id`, `event_type`,
   `occurred_at`, `city_id`, `data`; partition key `restaurant_id` for order
   events until orders carry `city_id`.
7. A duplicate `event_id` on any consumer is a no-op (`processed_events`).

## Cutover readiness (fail-closed)

`OrderCommandRouter` requires **all** of:

1. `ORDER_COMMAND_CUTOVER_ENABLED=true`
2. `ORDER_COMMAND_CUTOVER_READY=true` (only after 50k shadow parity)
3. the city or restaurant appears in `ORDER_COMMAND_CITIES` / `ORDER_COMMAND_RESTAURANTS`

Anything else stays on Laravel. The router is deliberately **not wired** into
checkout until Java owns pricing, discounts, and payment state.

## Adapter ports (already defined)

- `OrderRepository` — find by id, find by idempotency key, save with optimistic
  locking (`version`).
- `OutboxWriter` — append events inside the same transaction.
- `UnitOfWork` — explicit transaction boundary.

## Shadow-mode acceptance

For 50,000 replayed checkout requests across channels:

- identical status and totals,
- identical event types and ordering,
- no duplicate orders on replay,
- p99 latency no worse than the Laravel path + 20 ms,
- zero DLQ growth.

Only then does the route switch, city by city, behind a feature flag.
