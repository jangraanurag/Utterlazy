# 📐 UtterLazy v2 — Low-Level Design (LLD)

Schemas, endpoints, validations, state machines, and exact files for Wave 1.

---

## 1. Database — catalog-service (new tables)

### 1.1 `outbox`
| Column | Type | Notes |
|---|---|---|
| id | uuid PK | |
| event_id | uuid UNIQUE | dedupe id carried on Kafka |
| aggregate_type | varchar(50) | `order` \| `payment` \| `wa_placement` |
| aggregate_id | uuid | |
| event_type | varchar(80) | `order.created`, `order.status_changed`, … |
| payload | json | envelope body |
| city_id | uuid nullable | partition key |
| occurred_at | timestamp | |
| published_at | timestamp nullable | broker ack |
| attempts | smallint default 0 | |
| last_error | text nullable | |

Index: `(published_at, id)` for the publisher poll.

### 1.2 `processed_events`
| Column | Type | Notes |
|---|---|---|
| event_id | uuid | part of composite PK |
| consumer | varchar(80) | part of composite PK |
| processed_at | timestamp | |

### 1.3 `idempotency_keys`
| Column | Type | Notes |
|---|---|---|
| key | varchar(80) | composite PK |
| user_id | uuid | composite PK |
| order_id | uuid nullable | |
| response | json | stored checkout response |
| created_at | timestamp | TTL job deletes > 24h |

### 1.4 `orders.channel` (ALTER)
`channel` varchar(12) NOT NULL DEFAULT `'app'`, check `app|web|whatsapp`. Also `cancel_reason` varchar(40) nullable (Wave 1b consumes; column ships in Wave 1).

### 1.5 `wa_placements`
| Column | Type | Notes |
|---|---|---|
| id | uuid PK | |
| restaurant_id | uuid FK | |
| city_id | uuid FK | |
| daily_budget | decimal(12,2) | 100.00–50000.00 |
| cpc_bid | decimal(12,2) | ≥ floor at submit |
| spent_today | decimal(12,2) default 0 | reset at IST midnight |
| spent_date | date | day key for reset |
| start_at, end_at | datetime | end>start, span ≤ 90 days |
| creative_title | varchar(40) | 3–40 chars |
| creative_body | varchar(120) | 10–120, no phone, no "guaranteed" |
| status | enum | draft, pending_review, active, paused, exhausted, rejected |
| pinned_floor | decimal(12,2) nullable | copied at approval, immutable |
| approval_instance_id | uuid nullable | workflow instance |
| rejection_reason | varchar(300) nullable | 10–300 |

### 1.6 `wa_city_floors`
| Column | Type |
|---|---|
| city_id | uuid PK |
| floor_cpc | decimal(12,2) |
| max_slots | smallint default 5 |
| updated_by | uuid |
| updated_at | timestamp |

## 2. Database — whatsapp-service (`food_whatsapp`)

| Table | Columns |
|---|---|
| `wa_contacts` | wa_id PK (E.164), user_id nullable, language enum hi/en, last_seen |
| `wa_consents` | wa_id PK, utility bool default true, marketing bool default false, marketing_opted_at nullable |
| `wa_sessions` | wa_id PK, state varchar(40), context json, expires_at |
| `wa_messages` | wa_message_id PK, wa_id, direction in/out, payload json, created_at |
| `wa_carts` | wa_id PK, restaurant_id, lines json `[{menu_item_id, variant_id, add_on_item_ids[], qty, unit_price, special_instructions}]` |

## 3. APIs — catalog additions

### 3.1 Checkout delta (existing `POST /checkout` → `OrderController@checkout`)
- Optional body field `channel` in `app|web|whatsapp` (default `app`).
- When `channel=whatsapp`:
  - Header `Idempotency-Key` required, 8–80 chars.
  - Lookup `idempotency_keys` first → return stored response (same HTTP code).
  - `payment_mode` restricted to `prepaid|cod`.
  - WhatsApp-level caps enforced BEFORE calling checkout (see 5.2). Checkout rules otherwise untouched.
- On commit: insert `outbox` row (`order.events.v1`, `order.created`) in the same transaction.
- On success: upsert idempotency row with the response body.

### 3.2 `POST /api/wa-placements` (vendor)
Fields per §1.5. Creates `draft`, then `AccountManagementApprovalBridge::originate('wa_placement')`.
Client error if bid < current floor: 422 with `{floor_cpc}`.

### 3.3 `GET /api/wa-placements` (vendor) — own rows, latest first.

### 3.4 `POST /api/wa-placements/{id}/pause` (vendor) — only from `active`.

### 3.5 Admin (new, `admin.auth` with noted permission)
- `GET /api/admin/wa-placements` — `ads_ops`
- `POST /api/admin/wa-placements/{id}/reject` — `ads_ops`, body `reason` 10–300
- `GET /api/admin/wa-city-floors` — `ads_ops`
- `PUT /api/admin/wa-city-floors/{city_id}` — `ads_ops`, audited
- `GET /api/admin/command-center` — `city_ops`/`super_admin`, read-only city rows
- `GET /api/admin/whatsapp/sessions` — `whatsapp_ops`
- `POST /api/admin/whatsapp/sessions/{id}/assign` — `support_lead`/`whatsapp_ops`
- `POST /api/admin/staff` — `super_admin` only
- `POST /api/admin/staff/{id}/deactivate` — `super_admin` only

### 3.6 Internal
`GET /api/internal/wa-reco?city_id&limit=5` — service-token middleware (new `ServiceAuth` middleware, shared secret header `X-Service-Token`). Returns `{items:[{placement_id, restaurant_id, title, body, sponsored, bid}]}`. Paid placements first (active, budget-remaining, floor-ok, bid desc, capped by `max_slots`), then top organic restaurants fill the remaining limit; unpaid are never hidden.

## 4. WhatsApp service endpoints

- `GET /webhook/meta?hub.mode=subscribe&hub.verify_token=...` — verification echo.
- `POST /webhook/meta` — signature required; dedupe `wa_message_id`; enqueue state machine job.
- `GET /health`.

## 5. Validations

### 5.1 Placement form
| Field | Rule |
|---|---|
| city_id | required, restaurant operates there |
| daily_budget | numeric 100.00–50000.00 |
| cpc_bid | numeric, ≥ floor at submit time |
| start_at / end_at | end > start, span ≤ 90 days |
| creative_title | 3–40 chars |
| creative_body | 10–120 chars; reject on phone regex `(\+?91)?[6-9]\d{9}` or word `guaranteed` |
| destination | derived = that restaurant menu. No free URL |

### 5.2 WhatsApp pre-checkout caps (enforced in whatsapp-service)
| Field | Rule |
|---|---|
| language | `hi` \| `en`, asked once |
| address line1 | 5–200 chars |
| landmark | ≤ 120 |
| pincode | `^[1-9][0-9]{5}$` |
| lat / lng | −90..90 / −180..180 |
| cart lines | 1–50, single restaurant |
| qty per line | 1–20 |
| variant_id / add_on ids | must belong to that menu item |
| tip | ₹0–₹500 |
| special_instructions | ≤ 200 |
| phone OTP (bind wa_id→user) | 6 digits, 5 min expiry (catalog OTP), 5 WhatsApp attempts, token in Redis 30 min |
| order PIN | 4 digits, hashed at rest, 3 verify attempts, required before any WhatsApp/AI order |
| intent confidence | ≥ 0.75 else button menu |

### 5.3 Consent and template rules
- Utility consent gates status templates only.
- Marketing consent + active placement gate proactive reco.
- Outside 24h session: utility templates only.
- Free-text never allowed to place/cancel an order.

## 6. WhatsApp state machine

States: `WELCOME → LANGUAGE → SHARE_LOCATION → ADDRESS_PICK → NEAR_ME | NAME_SEARCH → MENU → ITEM_CONFIG → CART → CHECKOUT → PAYMENT → TRACK | HUMAN | OPT_OUT`, plus account link `PHONE → OTP` and order confirmation `PIN_SETUP | PIN_VERIFY` (auto-resume checkout).

- Each state = one class implementing `WaState::handle(WaMessage): WaReply`.
- `cancel_request` → creates complaint/support ticket, never sets order status.
- `human` → `support.ticket.v1` + assigns L1 by org unit.
- Context JSON in `wa_sessions` carries restaurant_id, address, cart snapshot.

## 7. AdminAuth roles (catalog `AdminAuth::ROLE_PERMISSIONS` additions)

| Role | Permissions |
|---|---|
| support_l1 | orders.view, complaints.view, whatsapp.handoff |
| support_l2 | + complaints.refund_originate |
| support_lead | + queue.assign, sla.view |
| city_ops | orders.view, rescue.view, command_center.view |
| rider_ops | riders.view/verify/documents, rider-payouts.view/originate |
| catalog_ops | restaurants.view/update, menu-items.view/update, categories.manage |
| onboarding_agent | onboarding.view |
| ads_ops | placements.review, floors.manage |
| whatsapp_ops | wa.sessions.view, wa.templates.view, wa.assign |
| fraud_analyst | fraud.view, fraud.flag |
| growth | coupons.view |
| auditor | all `*.view`, zero writes |

Staff record: name, email (unique), phone `+91[6-9][0-9]{9}`, role from list, `org_unit_id`, active. Deactivate ≠ delete.

## 8. Files (Wave 1)

### catalog-service
- `database/migrations/*_create_outbox_table.php`
- `database/migrations/*_create_processed_events_table.php`
- `database/migrations/*_create_idempotency_keys_table.php`
- `database/migrations/*_add_channel_and_cancel_reason_to_orders.php`
- `database/migrations/*_create_wa_placements_table.php`
- `database/migrations/*_create_wa_city_floors_table.php`
- `app/Models/Outbox.php`
- `app/Services/OutboxPublisher.php` (transport interface + Kafka/Redis transports)
- `app/Console/Commands/PublishOutbox.php` (`outbox:publish --daemon`)
- `app/Http/Middleware/ServiceAuth.php`
- `app/Http/Controllers/Api/WaPlacementController.php`
- `app/Http/Controllers/Api/AdminStaffController.php`
- `app/Http/Controllers/Api/CommandCenterController.php`
- `app/Http/Controllers/Api/InternalRecoController.php`
- `app/Services/AccountManagementApprovalBridge.php` — `wa_placement` finalizer
- `app/Http/Controllers/Api/OrderController.php` — channel + idempotency + outbox
- `app/Http/Controllers/Api/PaymentController.php` — payment outbox events
- `app/Http/Middleware/AdminAuth.php` — role table
- `routes/api.php`, `routes/console.php`, `config/services.php`

### whatsapp-service (new Laravel app)
- `app/Domain/Conversation/StateMachine.php` + `States/*.php`
- `app/Domain/Intent/IntentRouter.php` (Gemini tool schema)
- `app/Providers/WhatsAppProvider.php` + `MetaCloudProvider.php`
- `app/Http/Controllers/WebhookController.php`
- `app/Listeners/OrderEventListener.php` (consumes `order.events.v1` → templates)
- migrations for §2 tables; `routes/api.php`; Dockerfile; `.env.example`

### web
- `web/vendor-web/src/pages/WhatsAppPromotions.vue`
- `web/admin-web/src/pages/{CommandCenter,WhatsAppOps,PlacementReview,CityAdFloors,Workforce,StaffProvision}.vue`
- router + sidebar entries, permission-gated.

## 9. Contract tests (must pass before Wave 1 closes)

1. One checkout → exactly one outbox row in the same tx; rollback writes zero.
2. Publisher restart loses nothing, double-applies nothing (consumer dedupe).
3. Duplicate Meta webhook → one order (idempotency key).
4. Unpaid restaurant absent from `/api/internal/wa-reco`.
5. Active paid placement present with `sponsored: true`.
6. NEAR_ME returns unpaid open restaurant inside 10 km.
7. Marketing opt-out blocks push, does not block status template.
8. `support_l1` gets 403 on refund and placement approve.
9. Unapproved/rejected placement cannot spend.
10. Floor edit does not change `pinned_floor` on in-flight placement.
11. Existing app checkout without `channel` still returns an order (no regression).

---

## 10. Wave 1 implementation addendum (built)

### 10.1 Additional tables
- `wa_placement_events` — id, placement_id, city_id, event_type (impression|click), external_id, amount, occurred_at; unique `(placement_id, event_type, external_id)` for idempotent charging.
- `wa_workforce` — id, user_id (unique), city_id, shift_start, shift_end, queue_cap (1–40), active.
- `wa_handoffs` (whatsapp-service DB) — id, wa_id, order_id, reason, status (open|assigned|closed), assignee_id, timestamps.
- `wa_processed_events` (whatsapp-service DB) — event_id + consumer, dedupe for the event consumer.

### 10.2 Internal endpoints (catalog, `X-Service-Token`)
- `POST /api/internal/wa-impression` — `{placement_ids[], external_id}` → dedupe, `ads.placement.v1`-family impression event, no spend.
- `POST /api/internal/wa-click` — `{placement_id, external_id}` → one charge of `cpc_bid`, `spent_today += bid`, auto `exhausted` at budget, event emitted.
- `GET /api/internal/wa-reco` — via `WaRecoService::paidInventory()`.

### 10.3 Internal endpoints (whatsapp-service, `X-Service-Token`)
- `GET /api/internal/sessions` — sessions + consent + open handoff (catalog proxies for admin UI).
- `POST /api/internal/sessions/{waId}/assign` — assign handoff.
- `POST /api/internal/reco/push` — marketing-consent-gated paid carousel; logs impressions; moves session to NEAR_ME.
- `GET /api/internal/carts/{waId}` — cart inspection for ops.

### 10.4 Event consumer
`php artisan events:consume` reads `outbox:order.events.v1` + `outbox:payment.events.v1`
(Redis streams in dev; Kafka when `EVENT_BUS_DRIVER=kafka` + php-rdkafka).
Dedupe via `wa_processed_events`; utility consent only; templates mapped per status.

### 10.5 Admin/vendor surfaces
- Admin: Command Center, Placement Review, City Ad Floors, WhatsApp Ops, Workforce, Staff Provision.
- Vendor: WhatsApp Promotions (create/pause placement).
- Public: `GET /api/cities-public` for vendor city targeting.

### 10.6 Scheduled jobs
- `wa-placements:manage` every 5 min — daily spend rollover, budget exhaustion, campaign end pause.
- `idempotency:prune` hourly; `outbox:purge-processed` daily; `approvals:check-sla` unchanged.

---

## 11. Wave 1b — fairness implementation (built)

### 11.1 Cancellation + reversal
- `orders.cancel_reason` (shipped Wave 1) + `orders.cancel_note`.
- `OrderReversalService::reverse()` — formula reversal, no approval:
  - wallet → wallet credit + `WalletTransaction` + `payment_status=refunded`
  - prepaid with `razorpay_payment_id` → gateway refund (`RefundGateway` port, Razorpay adapter); failure → `refund_pending` with `refund_attempts` + `refund_last_error`
  - `refunds:retry-pending` every 15 min retries up to 10 attempts; `GET /admin/refunds/pending` + `POST /admin/refunds/{order}/retry` (`payments.manage`, audited)
  - unpaid/COD → released, reason stamped
- Wired into: `orders:cancel-stale` (`restaurant_timeout`), vendor reject (`restaurant_reject`),
  customer cancel (`customer`), `orders:no-rider-sweep` (`no_rider`), Razorpay `payment.failed` (`payment_failed`).

### 11.2 No-rider sweep
`orders:no-rider-sweep` (every minute):
- `ready_for_pickup`, `rider_id IS NULL`, `ready_marked_at ≤ now-8m` → `order.no_rider_alert` event
- `≤ now-15m` → cancel (`no_rider`) + reversal + status event

### 11.3 Rider fairness
- Wait pay: `handover_delay_minutes × rider_wait_pay_per_min`, capped; fallback total wait > 15 min.
- COD float cap: rider with delivered-COD outstanding + order total > `rider_cod_cap` (default ₹5,000)
  excluded from cash offers; prepaid offers unaffected.
- Commission/invoice queries select delivered/completed only — cancelled orders cannot be billed.

### 11.4 Supply gate
Checkout returns 422 with no charge when `block_checkout_without_riders` is on and zero verified,
online riders with fresh location exist. Default off; turn on per launch policy.

### 11.5 Deliberately still open (Wave 1b remainder)
- POD photo required to close a delivery dispute
- customer-unreachable script (3 masked calls → ticket → return pay)
- goodwill / food-waste claim origination in the approval bridge
- restaurant miss-rate surface for owner/AM
- rider no-strike on reported breakdown, new-rider exploration floor, deactivation approval

---

## 12. Wave 1b remainder — fairness close-out (built)

### 12.1 Proof of delivery
`require_pod_on_delivery` (default off): both `PATCH /orders/{order}/status` and
`POST /orders/{order}/verify-otp` reject `delivered` without `delivery_photo_path`.

### 12.2 Customer unreachable
- `POST /orders/{order}/customer-unreachable` (rider auth, out_for_delivery only) → `customer_unreachable_at`.
- `orders:unreachable-sweep` (every minute, 5-min window): complaint (`customer_unreachable`),
  `support.ticket.v1`, order `abandoned`, rider attempt pay (`rider_return_pay`, default ₹10).
- No rider strike; cash/COD untouched.

### 12.3 Food-waste claims
- `food_waste_claims` table; entity `food_waste_claim` in the finance approval engine.
- `POST /admin/orders/{order}/food-waste-claim` (`invoices.manage`): amount ≤ `orders.subtotal`,
  reason 10–300, photo from claim or POD, only for `no_rider` / `customer_unreachable`.
- Final approval credits `RestaurantPayoutBalance` once and marks the claim approved.
- Customer goodwill keeps using the existing complaint-refund approval path.

### 12.4 Restaurant miss rate
`GET /vendor/reliability` (30 days): total, timeouts, rejects, late prep, miss-rate %.
Vendor Dashboard card. Ads/promotions never alter the metric.

### 12.5 Rider fairness extensions
- Rejection reasons: `too_far|vehicle_breakdown|safety|other` stored on `rider_assignments`;
  breakdown before pickup is not a strike, order reassigns.
- Exploration floor: every 8th order prefers riders with `< 20` total deliveries inside the
  same radius when available.
- Deactivation: `POST /admin/riders/{rider}/deactivate` (`riders.documents`), reason 10–200,
  blocked while `current_order_id` is set, tokens revoked, audit row kept.

### 12.6 Event bus verification
`tests/wave1_smoke.php` injects a fake `OutboxTransport`: publish once, no republish,
attempts increment, max-retry → DLQ, exhausted rows never retried. 325/325 PASS.

### 12.7 Production topology
`docker-compose.prod.yml`: 3-broker KRaft Kafka (`replication=3`, `min.insync=2`, 12 partitions),
outbox publisher, whatsapp-service + consumer, Redis queues for worker/scheduler,
`SERVICE_TOKEN` + Kafka env wiring.


---

## 13. Wave 3 — analytics projector (built)

- Schema: `infra/clickhouse/schema.sql` (`order_events`, `payment_events`, `ad_events`, `city_daily`).
- Port: `AnalyticsSink`; adapter: `ClickHouseHttpSink` (`INSERT ... FORMAT JSONEachRow`).
- `AnalyticsProjector::project()` selects supported outbox event types not yet in
  `analytics_projected_events`, maps to fact rows, inserts, then marks the event.
- Failure: row is not marked → retried next run; ClickHouse `ReplacingMergeTree` dedupes on `event_id`.
- `analytics:project` scheduled every minute; exits immediately when `ANALYTICS_ENABLED=false`.
- Deploy: `--profile analytics` ClickHouse with init schema; see `docs/wave3-analytics.md`.

- City facts: `CityAnalyticsReader` port; `ClickHouseCityAnalytics` (query client),
  `MySqlCityAnalytics` (fallback), `FallbackCityAnalytics` (empty/error → MySQL).
  Command Center returns `metrics_source`. Live exception clocks stay on MySQL.

- Pricing preflight: `CheckPricingParity` job (allowlisted scope) → Java `/api/pricing/preflight`
  → `pricing_parity_checks`; `GET /admin/pricing-parity`, `pricing:parity-report`, daily purge.

- Account link: `PHONE` normalizes `wa_id`/typed number (`PhoneNormalizer`), requests catalog OTP,
  `OTP` verifies and stores the customer token in Redis (`WaTokenStore`, 30 min), then resumes
  checkout/tracking. Five failed attempts → `HUMAN` handoff. No token is written to the DB.
- `CheckoutState`/`TrackState` fall back to `PHONE` when the token expires — the customer is
  never silently stuck.

## 14. Ops incidents (built)

- Table `ops_incidents`: city, severity (sev1/2/3), category, title, description, status
  (open/ack/resolved), owner, opened_by, opened_at, ack_at, resolved_at, sla_due_at,
  sla_breached_at, resolution_note.
- `IncidentService`: create/ack/resolve/assign + `markBreaches()`; transitions domain-guarded.
- SLA: sev1 5m, sev2 15m, sev3 60m. `incidents:sweep` every minute, one breach stamp each.
- Events on `ops.incident.v1` (opened/acknowledged/assigned/resolved/sla_breached).
- API `/admin/incidents*` with `incidents.view|manage`; Command Center counts; Vue console.

## 15. PII audit (built)

- `pii_access_logs` (append-only): actor, role, action (view/export/download), resource,
  filters JSON, row_count, purpose, ip, user_agent, created_at. No `updated_at`.
- `PiiAccessLogger` is mandatory for instrumented admin exports; insert failure aborts the export.
- Instrumented: orders / revenue / riders / restaurant_report exports.
- `GET /admin/pii-audit` with `audit.view`; 24h summary; Admin PII Audit page (auditor read-only).

## 16. Ops shifts (built)

- `ops_shifts`: user, org_unit, city, role snapshot, starts/ends, status
  (scheduled/active/completed/cancelled), notes, cancelled_reason, created_by.
- `ShiftService`: create (12h cap, ≥ now-24h, no user overlap), activate, cancel (reason
  5–200), `onShift(city, org_unit, at)`, `coverage(city, date)`.
- API `/admin/shifts*` with `shifts.view|manage`; Shift Planner UI; Command Center
  `on_shift_agents` + per-city `agents_on_shift`.

## 17. Safety SOS (built)

- `sos_alerts`: rider, order, lat/lng, status (open/ack/resolved), incident_id,
  acknowledged_by/at, resolved_at, resolution_note, metadata, triggered_at.
- `SosService::trigger` dedupes 5 minutes, creates a SEV1 `safety` incident via
  `IncidentService`, emits `safety.sos_triggered` and calls `SosPartnerNotifier`.
- Partner: `WebhookSosPartnerNotifier` (config `SOS_PARTNER_WEBHOOK`, non-throwing).
- API `/rider/sos` + `/admin/sos*` with `sos.view|manage`; Command Center Open SOS;
  Incident Console SOS panel.

## 18. Internal OTP hardening (built)

- `OtpService`: issue/verify with **DB-backed per-phone** limits (3 per 10 min, 10 per day,
  5 verify attempts, 5-minute TTL). No dependency on the caller IP.
- `OtpSender` port + `SmsOtpSender` (Fast2SMS). Failed delivery deletes the OTP so it does not
  count against the user's throttle.
- `CustomerTokenIssuer`: find/create customer + Sanctum token (WhatsApp token name).
- Internal endpoints under service auth: `POST /api/internal/otp/request`,
  `POST /api/internal/otp/verify`. Public `/api/auth/otp/*` unchanged.
- WhatsApp `CatalogClient` uses internal endpoints with `X-Service-Token`, falling back to the
  public endpoints on 404 during rolling deploys.

## 19. ONDC seller core (built)

- `OndcSigner` / `OndcVerifier`: Ed25519 over `created|expires|base64(sha256(body))`,
  keyId `subscriber|key|ed25519`, ±300s skew, expiry + malformed-header rejection.
- `OndcCatalogMapper`: internal provider/items → Beckn `on_search` (context, bpp/providers,
  locations.gps, items with INR price, delivery fulfilment).
- `POST /api/ondc/search` verifies signature and returns `on_search` (inline for harness;
  outbound callback pending). `POST /api/ondc/confirm` always NACKs until certified.
- Config `ONDC_*`; disabled by default.

## 20. Ranker training pipeline (offline)

- `ranker:export-dataset`: delivered-order positives + same-city negatives; features
  (`bayes_rating`, `rating_count`, `distance_km`, `prep_time_minutes`, `order_count_7d`,
  `cancellation_rate`, `avg_price`, `is_new`). v0 uses export-time aggregates (documented leakage).
- `services/ranker/trainer.py`: group-aware split, standardized logistic regression,
  accuracy/AUC, `ranker-logreg-v1` JSON contract; `train.py` CLI.
- Tests: 5 trainer checks (signal learning, preference, schema/class validation, AUC).
- Model is offline only; serving requires an A/B gate.

## 21. Ranker model serving gate (built)

- `services/ranker/app/model.py`: model loading/validation (feature contract, AUC ≥ 0.60,
  accuracy ≥ 0.55), `candidate_features`, deterministic `bucket_for`, `select_mode`.
- `rank(..., scorer=...)` reuses eligibility/diversity/tie-break; model scorer returns
  probability + `{"model": score}` breakdown.
- `/rank` returns `scoring` (`policy|model|shadow_policy|policy_fallback`), `experiment`
  (flags/share/bucket/model version), and `shadow` (model ranking in shadow mode).
- Laravel `ranked-organic` accepts `assignment_key` and passes the new fields through.
- Defaults: model disabled; no model file means `policy_fallback` only when enabled.

## 22. Certification harness (built)

- `scripts/certify.sh`: offline gates (PHP smoke, WhatsApp tests/boot, pricing parity, Java core +
  Spring adapter, ranker, YAML, docs, optional frontends) and staging gates
  (`wave1_verify`, pricing report, shadow compare, ranker health).
- Writes `infra/certification/report-<utc>.{md,json}`; non-zero exit on FAIL; SKIP is explicit.
- Human sign-off items documented in `docs/certification.md`.

## 23. PII retention (built)

- `PiiRetentionService`: minimum 30-day window, `preview()` breakdown, `purge()` dry-run/apply,
  chunked deletes (1000), durable `pii_retention_runs` (actor, cutoff, dry_run, deleted, breakdown).
- `pii:retention` command: dry-run default; `--apply` requires `--confirm=DELETE`; optional `--actor`.
- Scope is `pii_access_logs` only; SOS/incidents/orders/audit trails are never touched.
- `GET /admin/pii-audit/retention` (`audit.view`) + PII Audit Retention runs table.

## 24. Cutover readiness gate (built)

- `CutoverGate::evaluate()` checks: cutover flag, certified flag, allowlist, ≥1 pricing sample in
  24h, zero pricing mismatches, Java health, SERVICE_TOKEN, certification artifact (fresh ≤7d,
  zero FAIL; optional unless configured).
- `cutover:check [--strict]`, `GET /admin/cutover/readiness`, Command Center READY/BLOCKED card.
- Advisory only: checkout remains on Laravel; flags alone cannot route traffic.

## 25. Order projection (built)

- `projected_orders` read model: Java OrderSnapshot fields + pricing columns + version/event provenance.
- `OrderProjectionService::apply()`: transactional dedupe (`processed_events`, consumer
  `order-projection`) + monotonic version upsert; unsupported events ignored.
- `projection:consume` reads `outbox:order.events.v1` (Redis; Kafka consumer pending for prod);
  `projection:status` and `/admin/projection/status`.
- Cutover gate requires ≥1 projected order in 24h. Live `orders` table is never touched.

## 26. Shift auto-rotation (built)

- `ShiftRotationPlanner::plan()`: active workforce → deterministic weekly roster; respects
  existing shifts, one shift/agent/day at per-day=1, 12h clamp, reports gaps.
- `apply()`: transactional overlap re-check; re-apply safe (created=0/skipped=N); creator recorded.
- `shifts:rotate --week-start --city --org-unit --start-time --hours --per-day --days --apply
  --actor --fail-on-gap`; `POST /admin/shifts/rotate`; Shift Planner Preview/Apply + gap list.

## 27. Rotation gap incidents (built)

- `RotationGapIncidentService::raise(plan, actor)`: one SEV3 `staffing` incident per gap day/city,
  titled deterministically, owned by the first `support_lead`; deduped while open/ack.
- Runs only when a rotation is applied (CLI `--apply`, API `apply=true`); dry-run never creates.
- `IncidentService`/controller gained the `staffing` category.

## 28. Projection transports (built)

- `OrderEventSource` port: `RedisOrderEventSource` (stream cursor) and `KafkaOrderEventSource`
  (rdkafka, group `utterlazy-order-projection`, manual commit after projection).
- `OrderEventSourceFactory` (`PROJECTION_TRANSPORT=redis|kafka|auto`); `ProjectionRunner`
  polls → applies → acks, stops the batch without acking on failure.
- Catalog Dockerfile installs librdkafka/php-rdkafka; prod projection-consumer uses Kafka.

## 29. Incident escalation (built)

- `ops_incidents.escalation_level` (0/1/2) + `last_escalated_at`.
- `IncidentService::escalateAtRisk()`: open incidents past 50% of their SLA window → level 1,
  `ops_incident.sla_at_risk`, assign support_lead if unowned.
- `markBreaches()`: level 2, `sla_breached_at`, `ops_incident.sla_breached`, team owner if unowned.
- `incidents:sweep` runs both each minute; events idempotent.

## 30. ONDC outbound callbacks (built)

- `OndcCallbackClient`: fresh Ed25519 signature per callback, POST to `context.bap_uri`,
  injectable transport for tests.
- `SendOndcCallback` job: 3 tries, 5s backoff; disabled unless `ONDC_CALLBACK_ENABLED=true`.
- Search dispatches the job and still returns inline `on_search` for the harness.

## 31. GPS anomaly guard (built)

- `GpsSpeedGuard`: haversine speed between pings, reject >120 km/h; first ping allowed.
- `updateLocation` on anomaly increments `riders.gps_anomaly_count`, sets `gps_flagged_at`, returns
  422 `gps_anomaly` without moving the stored location.
- Assignment candidates exclude riders with `gps_anomaly_count >= 3`; a plausible ping resets the count.

## 32. City capacity caps (built)

- `CityCapacityGuard::allow(city, cap)`: counts orders in the last hour; cap ≤ 0 disables.
- Checkout reads `city_hourly_order_cap` and per-city `city_hourly_order_cap_<uuid>` from SystemConfig;
  over cap → 422 `city_capacity`, no payment taken.

## 33. Scheduled closure + session revoke (built)

- `scheduled-orders:cancel-closed` every 5 min cancels `scheduled` orders ≤60 min ahead when the
  restaurant is inactive/closed; `scheduled-orders:process` also refuses to place at the slot.
- Restaurant bank-detail changes and rider UPI changes revoke existing tokens (`session_revoked`).

## 34. OpenSearch organic search (built)

- `OpenSearchClient` (raw HTTP, injectable transport): `search()` + `bulk()`.
- `RestaurantSearchDocument`: id/name/cuisine/rating/city/geo/flags.
- `search:index-restaurants` daily bulk; `GET /api/search/fast` uses OpenSearch when enabled and
  falls back to the existing MySQL search on disabled/error.
- Compose `--profile search` single-node OpenSearch (security plugin disabled, internal only).

## 35. On-call ownership (built)

- `IncidentService::teamOwner(role, city)` prefers a `support_lead` whose `OpsShift` covers now in
  the incident's city, then falls back to the oldest admin with that role.
- Used by at-risk (50% SLA) and breach escalation; shift roster is the on-call source of truth.

## 36. Dispatch core (built, groundwork)

- `GeoCell` geohash + haversine; `RiderCandidate`/`OrderStop` records.
- `DispatchPolicy`: filters (offline/unverified/stale/overloaded/>6km/excluded), weighted score,
  new-rider +0.05 and every-8th-assignment exploration round, deterministic tie-break.
- `BatchPlanner`: greedy 1–3 order batches by pickup/drop proximity.
- Self-test 20 checks; no Redis/Kafka/Spring integration yet.

## 37. Shard routing groundwork (built)

- `ShardRouter` SHA-256 city→shard, deterministic/range-tested; primitive for the future orders DB split.

## 38. Dispatch REST service (built)

- Spring Boot adapter over dispatch-core: `POST /api/dispatch/assign` (ranked candidates with
  breakdown), `POST /api/dispatch/batch` (1–3 order batches), `GET /api/dispatch/health`.
- Stateless; port 8004; compose profile `dispatch`; MockMvc tests (4).
- Redis GEO ingestion, offer timers and `dispatch.assignment.v1` remain for the live dispatch service.

## 39. Exploration tuning + guardrails (built)

- `ExplorationTuner` recommends `rider_exploration_every` from the observed share of assignments
  to <20-trip riders (cold start uses baseline 8); `riders:tune-exploration --apply` persists to
  SystemConfig and assignment reads it.
- `services/ranker/evaluate.py` gate: AUC ≥ min and ≥ baseline + margin; exits non-zero on failure.
- Incident escalation now prefers on-shift lead → OrgRole backup → any role holder.

## 40. ONDC select/init (built)

- `OndcController::select`: signed context, menu lookup, quote via PricingParityService
  (items+tax+delivery), `on_select`, optional signed callback. No order writes.
- `OndcController::init`: `on_init` with `ON-ORDER` payment terms. No order writes.
- `OndcOrderMapper` pure builders; `confirm` remains `501 ONDC_CONFIRM_NOT_ENABLED`.

## 41. ClickHouse time-travel features (v1, built)

- `ClickHouseFeatureExporter::asOf(timestamp, city, limit)`: per-restaurant orders/delivered/
  cancelled/GMV up to the timestamp; CSV export. Static attributes still join from MySQL.
- `ranker:export-clickhouse` command; no ClickHouse running → command fails clearly.
