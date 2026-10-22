# ✅ UtterLazy v2 — Master Build Checklist

> Tick order = build order. Do not skip a gate. "1b" is not optional polish; it is the nobody-suffers pack.

## Wave 1 — Backbone + WhatsApp channel + ops

### 1.1 Kafka + outbox (catalog)
- [x] Migration: `outbox` table
- [x] Migration: `processed_events` table
- [x] Migration: `idempotency_keys` table
- [x] `OutboxPublisher` service with transport interface
- [x] `KafkaTransport` (php-rdkafka) + `RedisTransport` (dev stream fallback)
- [x] `outbox:publish --daemon` command + worker container in compose
- [x] Kafka (KRaft) + DLQ topics in docker-compose
- [x] Emit `order.events.v1` (order.created) in checkout transaction (OrderObserver)
- [x] Emit `order.events.v1` on every status change
- [x] Emit `payment.events.v1` on prepaid verify, COD confirm, refund
- [x] Consumer dedupe via `processed_events`
- [x] Test: one checkout = one outbox row, rollback = zero (observer inside tx; smoke covers enqueue)

### 1.2 Channel + idempotency
- [x] Migration: `orders.channel` (+ `cancel_reason`)
- [x] Checkout accepts `channel` (default `app`)
- [x] `Idempotency-Key` required for `channel=whatsapp`
- [x] Replay returns stored response, no second order (in-transaction guard)
- [x] TTL cleanup job for keys > 24h (`idempotency:prune`, hourly)
- [x] Test: duplicate webhook/checkout → one order (insertOrIgnore race semantics, smoke-tested)

### 1.3 whatsapp-service
- [x] New Laravel app skeleton + Dockerfile + own DB schema
- [x] Meta webhook: verify token + signature + message dedupe
- [x] `wa_contacts`, `wa_consents`, `wa_sessions`, `wa_messages`, `wa_carts` tables
- [x] State machine + one class per state (WELCOME→…→TRACK/HUMAN/OPT_OUT)
- [x] Intent router (Gemini tool schema slot, <0.75 → buttons)
- [x] Language hi/en, asked once
- [x] Account link via phone OTP: `PHONE`/`OTP` states, token in Redis (30 min), 5 attempts, auto-resume checkout/track
- [x] WhatsApp order PIN: setup/verify states, hashed PIN, 3 attempts, AI-composed orders cannot place without it
- [x] Internal OTP endpoints + DB per-phone limits (no single-egress-IP throttle), WhatsApp client wired with 404 fallback
- [x] WhatsApp helper tests (13 checks) wired into CI
- [x] Address capture (line1 5–200, landmark ≤120, pincode regex, lat/lng)
- [x] NEAR_ME + name search = organic catalog calls
- [x] Cart caps: 1–50 lines, qty 1–20, ids belong to item
- [x] Checkout via catalog with `channel=whatsapp` + idempotency key
- [x] Prepaid → payment link (app `/pay/{order}` deep link); COD → confirm message (native Razorpay payment-link API later)
- [x] Status templates from `order.events.v1` (`events:consume` + `OrderEventListener`, consent-gated)
- [x] Utility vs marketing consent separated; opt-out = marketing only
- [x] 24h session window; outside → utility templates only (session TTL + state reset)
- [x] Human handoff → handoff row + `support.ticket.v1` published (auto L1 routing via org unit = Wave 1b)
- [x] No phone numbers in any message (no phone rendering anywhere)

### 1.4 Placements + floors
- [x] `wa_placements` + `wa_city_floors` tables
- [x] Vendor `POST/GET /api/wa-placements` + pause
- [x] Draft → `AccountManagementApprovalBridge` (`wa_placement`)
- [x] Finalizer sets `active` + copies `pinned_floor`
- [x] `ads.placement.v1` events
- [x] Internal reco endpoint (service token): active, budget, floor, bid desc, max_slots, sponsored flag
- [x] Impression/click → Kafka + `spent_today` + idempotent click charging
- [x] Auto-pause on budget exhaustion (scheduler every 5 min + on click); daily budget rollover
- [x] Floor edit audited; never rewrites `pinned_floor`
- [x] Test: unpaid absent, paid present, sponsored=true, bid-below-floor excluded, budget-exhausted excluded (smoke 325/325)

### 1.5 Admin roles + screens
- [x] `AdminAuth` roles: support_l1, support_l2, support_lead, city_ops, rider_ops, catalog_ops, onboarding_agent, ads_ops, whatsapp_ops, fraud_analyst, growth, auditor
- [x] Staff provision + deactivate (super_admin only)
- [x] Command Center (city rows, owner per row — global order exceptions + city WhatsApp health)
- [x] WhatsApp Ops (sessions, consent state, handoff, assignment) — Vue page + proxy API
- [x] Placement review (approve via org engine, reject 10–300) — Vue page + API
- [x] City ad floors (audited; API done)
- [x] Workforce (agent/shift/queue cap 1–40) — table, API, Vue page
- [x] Sidebar gated by permission (mirrors AdminAuth; API still enforces)
- [x] Test: support_l1 lacks placements.review + complaints.refund_originate; auditor read-only (smoke)

### 1.6 Closing gates (all must pass)
- [x] One-command certification: `MODE=offline|staging ./scripts/certify.sh` (Markdown + JSON report)
- [x] Offline run: 11 PASS / 0 FAIL / 2 SKIP (docker absent, staging offline); CI covers Maven/frontends
- [x] CI workflow `.github/workflows/wave1-gates.yml`: PHP lint + smoke, WhatsApp boot, Java core + adapter tests, frontend builds, compose validation
- [x] Publisher restart/retry/DLQ semantics proven with injected transport (325/325 smoke)
- [x] Marketing opt-out blocks push, not status (consent model separated)
- [x] Existing app checkout unchanged (no `channel`) still works
- [x] PHP lint clean on changed files
- [x] Docs: architecture-v2, hld, lld, build-checklist committed
- [x] Wave 1 smoke: 41/41 PASS (outbox, idempotency race, placement rules, paid-only reco, role matrix)

## Wave 1b — Nobody suffers (before city scale)
- [x] Auto-cancel `pending` → full prepaid reversal (`OrderReversalService`: wallet credit / Razorpay refund / refund_pending flag) + event; COD untouched
- [x] `cancel_reason` on all paths: restaurant_timeout, restaurant_reject, no_rider, customer, payment_failed (+ `cancel_note`); fraud reserved for Wave 2
- [x] Commission/payout queries select delivered/completed only → platform-cancelled orders cannot be charged or paid out
- [x] Ready + no rider: `orders:no-rider-sweep` alerts at 8 min, cancels + reverses at 15 min (every minute)
- [x] Checkout supply gate behind `block_checkout_without_riders` (default off): zero online riders → 422, no charge
- [x] Rider wait pay uses `handover_delay_minutes` × configured rate (capped), falls back to >15 min total wait
- [x] COD outstanding cap (default ₹5,000): rider above cap is excluded from cash offers; prepaid unaffected
- [x] POD photo required to close delivery when `require_pod_on_delivery` is on (both OTP and status paths)
- [x] Customer unreachable: rider endpoint → 5-min sweep → complaint + `support.ticket.v1` + abandon + rider return pay
- [x] Restaurant reject reason required (`oos|closed|capacity|other`, notes 10–120 for other) + reversal
- [x] Command Center: unaccepted>3m, ready-no-rider>8m, prepaid stuck>30m, COD outstanding, outbox lag
- [x] Food-waste claim entity in finance approval engine (amount ≤ food subtotal, photo, finalizer credits payout balance); goodwill uses existing complaint-refund approval
- [x] `GET /vendor/reliability` (30-day timeouts/rejects/late-prep/miss rate) + Dashboard card; ads never change the metric
- [x] Rejection reason captured (`too_far|vehicle_breakdown|safety|other`); breakdown before pickup is recorded, order reassigned, no strike
- [x] Every 8th order prefers <20-trip riders in radius when available (deterministic, never starves seniors)
- [x] Rider deactivation: `riders.documents` permission, reason 10–200, active delivery blocked, tokens revoked, audit kept

## Wave 2 — Scale without changing shares

### 2.1 Order-command groundwork (built)
- [x] Framework-free Java domain core: aggregate, state machine, place-order + transition services
- [x] Idempotency by `(user_id, key)`, WhatsApp key required, channel enum, line limits
- [x] Ports: `OrderRepository`, `OutboxWriter`, `UnitOfWork` (state + events commit together)
- [x] Self-test 23/23 (`./scripts/order_core_test.sh`)
- [x] Contract + invariants documented (`docs/wave2-order-command.md`)
- [x] Pricing parity contract: PHP mirror + Java `PricingCalculator` + 10 golden vectors (`./scripts/pricing_parity.sh`)
- [x] Pricing parity CI job + docs/pricing-parity.md (rounding, in/out of scope, cutover gate)
- [x] Shadow orders persist the full pricing breakdown; comparator checks tax/discount/fees/commission/payout
- [x] Production pricing preflight on real orders (allowlisted scope, no writes moved)
- [x] `pricing_parity_checks` + admin summary + Command Center rate card + `pricing:parity-report` / purge
- [x] Cutover readiness gate: flags, allowlist, 24h pricing parity, Java health, certification artifact
- [x] `cutover:check` + `/admin/cutover/readiness` + Command Center card
- [ ] 7-day 100% match certification before `ORDER_COMMAND_CUTOVER_READY=true`
- [x] Java adapter tests 10/10, core self-test 28/28
- [x] Spring adapter: REST (`POST /orders`, status transitions, GET), JDBC repo with optimistic locking, transactional outbox writer + Kafka publisher
- [x] Adapter integration tests: MockMvc + H2, 8/8 (place, replay, validation, conflict, cancel reason, 404, health, outbox row)
- [x] Shadow profile in prod compose (`--profile wave2`, outbox publisher disabled)
- [x] Shadow mirror job on order create + every status change (`MirrorOrderToOrderCommand`, after commit)
- [x] Parity comparator `order-command:compare` (status, channel, total, lines; `--fail-on-mismatch`)
- [ ] Shadow mode parity (50k replayed requests) — needs staging
- [x] Geography model: `orders.city_id` + `restaurants.city_id` + `cities:backfill` (unambiguous only)
- [x] Per-city order metrics in Command Center (unaccepted, ready-no-rider, COD, platform cancels)
- [x] Cutover router (`city`/`restaurant` allowlists, fails closed; not wired until pricing parity)
- [x] Java→Laravel projection read model (`projected_orders`): dedupe, monotonic version, transactional
- [x] `projection:consume` / `projection:status` / `/admin/projection/status` + Command Center card
- [x] Cutover gate requires projected orders in 24h
- [x] Kafka projection consumer (`KafkaOrderEventSource` via php-rdkafka), offsets committed after write
- [x] Pluggable transport factory + runner tests; Redis remains dev/staging fallback
- [ ] City-flagged cutover; Laravel checkout becomes proxy (pricing/payment ownership first)

### 2.2 Remaining Wave 2
- [ ] Java order-command: checkout proxy → owner
- [x] Idempotency store in order service (unique user+key, replay-safe)
- [ ] Orders DB split from catalog, shard key city_id (columns + `ShardRouter` groundwork done)
- [x] Dispatch policy/batching core (geohash, scoring, exploration, batching; 20 checks)
- [x] Dispatch REST service (assign/batch/health, MockMvc tests)
- [x] Offer timer policy: TTL, accept/reject/expire, idempotent expiry, pending-rider exclusion (10 checks)
- [ ] Redis GEO/H3 ingestion, persistent offer timers, `dispatch.assignment.v1`, live assignment persistence
- [x] OpenSearch organic search: raw HTTP client, bulk indexer, `/search/fast`, MySQL fallback
- [ ] Incremental index sync from catalog events + relevance tuning
- [ ] Early-dispatch wait pay → platform (needs dispatch)
- [x] Fake-GPS hold: 120 km/h anomaly guard, 3 strikes excludes rider from assignments
- [x] City hourly capacity caps (`city_hourly_order_cap`, per-city override) with 422/no-charge
- [x] Scheduled orders cancelled at T-60 (and at slot) when the restaurant is closed
- [x] Session revoke on restaurant bank-detail change and rider UPI change

## Wave 3 — Long view

### 3.1 Analytics read model (built)
- [x] ClickHouse schema: `order_events`, `payment_events`, `ad_events`, `city_daily` (ReplacingMergeTree)
- [x] `AnalyticsSink` port + `ClickHouseHttpSink` (JSONEachRow)
- [x] `AnalyticsProjector`: outbox → facts, one row per event, failed inserts retried
- [x] `analytics:project` command every minute (no-op when disabled) + `analytics_projected_events`
- [x] `--profile analytics` ClickHouse container in dev + prod
- [x] Tests: 3 event types, replay no-op, sink failure retry, disabled no-op (325/325 smoke)
- [x] Command Center daily facts from ClickHouse `city_daily` with MySQL fallback + `metrics_source` label
- [x] `city_daily_mv` materialized view from `order_events` (backfill SQL documented)

### 3.2 Organic ranker (built)
- [x] Python ranker core (Bayesian rating, distance, prep, popularity, reliability, price, exploration)
- [x] Paid partners get a +0.05 disclosed preference; unpaid candidates remain in organic rank
- [x] FastAPI service + Dockerfile + `--profile ranker` (dev + prod)
- [x] 17/17 tests (13 core dependency-free + 4 API) + CI job
- [x] Laravel `RankerClient` + `GET /api/recommendations/ranked-organic` with `php_fallback`
- [x] Dataset exporter: labeled positives + same-city negatives with shared feature math
- [x] Dependency-free trainer: group-aware split, logistic baseline, AUC, versioned model JSON
- [x] Trainer tests (5) + `scripts/ranker_train.sh`; model intentionally not served yet
- [x] Model serving gate: feature/metric validation, deterministic bucket, shadow mode, policy fallback
- [x] `/rank` scoring/experiment/shadow telemetry + Laravel passthrough (`assignment_key`)
- [x] ClickHouse as-of feature export (counts/GMV, city filter, CSV)
- [x] Guardrailed model promote/rollback tooling (`promote.py`, backup + `.rejected` archive)
- [ ] Full time-travel training join + online metrics + automatic rollback

### 3.3 Remaining Wave 3
- [x] ONDC seller protocol core: Ed25519 signed search, `on_search` catalog mapper, confirm NACKed
- [x] ONDC outbound signed callbacks (queued, retries, injectable transport, tests)
- [x] ONDC select/init contract (signed quote + on_init, no order writes)
- [ ] ONDC confirm order creation, payments/RTO (requires certification)
- [x] Exploration frequency tuned from assignment data (`riders:tune-exploration`, SystemConfig-driven)
- [x] Per-city incident console: SEV1–3 SLA clocks, owner, ack/resolve/assign, audit + `ops.incident.v1`
- [x] Incident sweep every minute (breach once), Command Center counts, admin Vue page, role matrix

## Wave 4 — Audit-ready company
- [x] Ops shifts on org units: 12h cap, overlap guard, coverage/on-shift queries, activate/cancel
- [x] Shift Planner UI + Command Center agents-on-shift (global + per city)
- [x] Auto-generated weekly rotations (`shifts:rotate`, dry-run default, overlap-safe, gap reporting)
- [x] Rotation gaps → owned SEV3 `staffing` incidents (deduped), support_lead assignment
- [x] Incident escalation: 50% SLA at-risk (level 1) + breach (level 2), team ownership, idempotent events
- [x] On-call lead selection: on-shift support_lead → OrgRole backup → role fallback (named second level)
- [x] Ranker guardrails: evaluate candidate vs baseline (AUC gate + margin), non-zero on failure
- [x] PII access/export audit log (append-only, mandatory for instrumented admin exports)
- [x] Auditor API + Admin **PII Audit** page (audit.view; read-only)
- [x] Manual retention command: dry-run default, `--confirm=DELETE`, min 30 days, chunked, run history
- [x] Auditor retention-run history API + PII Audit UI section
- [ ] Legal sign-off on retention window (default 180 days)
- [x] `safety.sos.v1`: rider SOS → SEV1 safety incident + outbox event + partner webhook hook
- [x] 5-minute dedupe, ack/resolve, Command Center card, Incident Console SOS panel
- [ ] Named insurance/emergency partner contract + SLA signed

## Never
- [ ] Billion-order launch claim
- [ ] Hiding unpaid restaurants from search (policy: never hide; paid preference only)
- [ ] AI placing/cancelling without the customer's 4-digit PIN
- [ ] Super-admin money bypass
- [ ] BNPL / postpaid / new wallet
- [ ] Fines without a formula
