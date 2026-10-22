# 🚀 Wave 1 Deploy & Verification Runbook

Operator steps for the catalog + WhatsApp + event backbone. No product behaviour is removed by this deploy; the old order path keeps working even if the new services are stopped.

---

## 1. Prerequisites

- Docker + Compose plugin on the host, repo at `/root/utterlazy`.
- `docker-compose.prod.yml` present, MySQL backup taken.
- Env (host `.env` or exported):

```bash
DB_PASSWORD=...
SERVICE_TOKEN=$(openssl rand -hex 32)        # shared catalog <-> whatsapp
WA_APP_KEY=base64:$(openssl rand -base64 32) # whatsapp APP_KEY
RAZORPAY_KEY_ID=... RAZORPAY_KEY_SECRET=...
META_PHONE_NUMBER_ID=... META_ACCESS_TOKEN=... META_APP_SECRET=...
META_VERIFY_TOKEN=utterlazy_wa_webhook
GEMINI_API_KEY=...
WA_PAYMENT_LINK_BASE=https://utterlazy.com/pay
```

- Meta app: webhook URL `https://wa.utterlazy.com/api/webhook/meta` (or your host path),
  verify token = `META_VERIFY_TOKEN`. Subscribe to `messages` + `message_status`.
- Approved utility templates: `order_status_update`, `order_delivered`, `order_cancelled`,
  `payment_received` (two body params: order id, status).

## 2. Deploy

```bash
cd /root/utterlazy
./scripts/wave1_deploy.sh
```

What it does, in order: build images → start MySQL/Redis/3-broker Kafka → migrate catalog →
migrate WhatsApp → start app services + publisher + consumer → clear caches → health checks →
Kafka reachability + outbox count.

Idempotent: re-running is safe.

### SMS OTP dependency

WhatsApp account linking uses the catalog OTP endpoints, which send via Fast2SMS.
Configure `FAST2SMS_API_KEY` and confirm delivery before opening the channel. If SMS fails,
the customer sees a retry message and can ask for an agent — checkout is not attempted.

WhatsApp uses the internal service-token OTP endpoints (`/api/internal/otp/*`) with
DB-backed per-phone limits: 3 requests per 10 minutes, 10 per day, 5 invalid attempts, 5-minute
TTL. The public `/api/auth/otp/*` endpoints remain IP-throttled for app/web.

## 3. Verify (gate before Wave 2)

```bash
SERVICE_TOKEN=... CITY_ID=<active-city-uuid> ./scripts/wave1_verify.sh
```

Checks: health, all new tables, Kafka topics, outbox lag (<50 rows, oldest <120s),
paid-only reco payload, idempotency uniqueness, refunds always have a cancel reason,
no negative totals, consumer dedupe.

Manual checks:

```bash
# Duplicate webhook must not double-order:
curl -X POST "$WHATSAPP_WEBHOOK" -H "X-Hub-Signature-256: sha256=..." \
  --data @meta_payload.json   # run twice; orders count +1 only

# Existing app checkout unchanged:
curl -X POST "$CATALOG/api/checkout" -H "Authorization: Bearer <user-token>" \
  -H "Content-Type: application/json" -d @checkout.json   # 201, channel=app

# WhatsApp checkout requires the key:
curl -X POST "$CATALOG/api/checkout" -H "Authorization: Bearer <user-token>" \
  -d '{"channel":"whatsapp", ...}'                        # 422 without Idempotency-Key
```

## 4. Monitoring (first 48h)

```sql
-- outbox health
SELECT COUNT(*) pending, COALESCE(MIN(occurred_at), NOW()) oldest
FROM outbox WHERE published_at IS NULL;

-- refunds that need finance retry (job retries every 15 min, max 10)
SELECT id, grand_total, cancel_reason, refund_attempts, refund_last_error, refund_retried_at
FROM orders WHERE payment_status = 'refund_pending' ORDER BY refund_retried_at;

-- exhausted: finance must resolve these manually
SELECT id, grand_total, refund_last_error FROM orders
WHERE payment_status = 'refund_pending' AND refund_attempts >= 10;

-- manual retry (also available in admin API)
-- php artisan refunds:retry-pending --limit=50

-- no-rider and timeout pressure
SELECT cancel_reason, COUNT(*) FROM orders
WHERE created_at >= NOW() - INTERVAL 1 DAY AND cancel_reason IS NOT NULL
GROUP BY cancel_reason;

-- WhatsApp paid spend vs budget
SELECT id, status, spent_today, daily_budget FROM wa_placements
WHERE status IN ('active','exhausted') ORDER BY spent_today DESC;
```

Kafka:

```bash
docker compose -f docker-compose.prod.yml exec kafka-1 \
  kafka-topics.sh --bootstrap-server kafka-1:9092 --describe --topic utterlazy.order.events.v1
docker compose -f docker-compose.prod.yml exec kafka-1 \
  kafka-topics.sh --bootstrap-server kafka-1:9092 --list | grep dlq
```

## 5. Rollback

No destructive step to undo.

```bash
docker compose -f docker-compose.prod.yml stop whatsapp-consumer whatsapp-service outbox-publisher
```

- Catalog keeps serving app/web orders; outbox rows accumulate (published on restart).
- Database columns/tables are additive; leave them in place.
- Kafka containers can stay up; they hold no source of truth.
- To stop the channel only: remove the Meta webhook subscription.

## 6. Wave 2 go/no-go

All must hold for 24–48h:

- [ ] 0 duplicate orders from duplicate webhooks
- [ ] outbox pending < 50, oldest < 120s, DLQ empty
- [ ] 0 `refund_pending` older than 1 hour
- [ ] WhatsApp utility template failure rate < 2%
- [ ] `wa-reco` returns only sponsored inventory (verified daily)
- [ ] No P1 incidents tied to the new services
- [ ] MySQL backup verified restorable

Then start Wave 2: Java order-command, dispatch, orders DB split by `city_id`, OpenSearch.

---

## 7. Wave 2 shadow mode (do not route traffic)

Before shadowing, map geography (`orders.city_id` powers per-city ops and cutover):

```bash
# dry run first; only unambiguous district/state matches are applied
docker compose -f docker-compose.prod.yml exec -T catalog-service \
  php artisan cities:backfill --dry-run --orders
docker compose -f docker-compose.prod.yml exec -T catalog-service \
  php artisan cities:backfill --orders
```

Ambiguous or unmatched restaurants are skipped — never guessed. Re-run after
cleaning `restaurants.district`/`state`.

The Java order-command service is built and tested but **not a write owner**. Shadow mode
records the same commands on a separate database and lets you compare behaviour.

```bash
# Shadow DB only (never production): if food_orders predates pricing columns, recreate it.
# schema.sql owns this database and shadow data is disposable.
# docker compose exec mysql mysql -uroot -p"$DB_PASSWORD" \
#   -e "DROP DATABASE IF EXISTS food_orders; CREATE DATABASE food_orders;"

# 1. Start the shadow service (profile keeps it out of normal deploys)
docker compose -f docker-compose.prod.yml --profile wave2 up -d order-command

# 2. Health
curl -fsS http://127.0.0.1:8003/api/orders/health

# 3. Send a mirrored request (same body the app uses)
curl -X POST http://127.0.0.1:8003/api/orders \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: shadow-$(date +%s)" \
  -d '{"userId":"<uuid>","restaurantId":"<uuid>","cityId":"<uuid>","channel":"app",
       "lines":[{"menuItemId":"<uuid>","quantity":1,"unitPrice":"199.00"}]}'

# 4. Inspect shadow state
docker compose -f docker-compose.prod.yml exec mysql \
  mysql -uroot -p"$DB_PASSWORD" food_orders \
  -e "SELECT id,status,grand_total,version FROM orders_v2 ORDER BY created_at DESC LIMIT 5;"
docker compose -f docker-compose.prod.yml exec mysql \
  mysql -uroot -p"$DB_PASSWORD" food_orders \
  -e "SELECT event_type,COUNT(*) FROM outbox_v2 GROUP BY event_type;"
```

Enable mirroring (catalog + queue worker) and compare:

```bash
# In the host env, then recreate the two services:
ORDER_COMMAND_SHADOW_ENABLED=true docker compose -f docker-compose.prod.yml up -d catalog-service queue-worker

# Every order create/status now mirrors to the shadow service via a queued job.
# Compare the last 24h (fails CI/release if anything diverges):
docker compose -f docker-compose.prod.yml exec -T catalog-service \
  php artisan order-command:compare --hours=24 --limit=500 --fail-on-mismatch
```

### Pricing certification (no writes move)

```bash
# host env, then recreate catalog + queue worker
ORDER_COMMAND_CUTOVER_ENABLED=true
ORDER_COMMAND_CITIES=<single-city-uuid>   # or ORDER_COMMAND_RESTAURANTS=<restaurant-uuid>
# leave ORDER_COMMAND_CUTOVER_READY=false

docker compose -f docker-compose.prod.yml up -d catalog-service queue-worker
docker compose -f docker-compose.prod.yml exec -T catalog-service \
  php artisan pricing:parity-report --hours=24
```

Every in-scope order runs a Java pricing preflight after commit. Checkout is never blocked.
Requirement: 7 days, 100% matched, zero error rows before `ORDER_COMMAND_CUTOVER_READY=true`.

Shadow rules:

- `OUTBOX_ENABLED=false` — the shadow service must not publish duplicate events.
- Mirroring is off by default; turning it on must never change a customer response.
- The queue worker must be running, otherwise mirrors are deferred (comparator reports `missing`).
- Never point `nginx` or `catalog-service` at port 8003.
- Compare responses and `outbox_v2` payloads with the Laravel path for replayed requests.
- When parity passes (50k requests, zero mismatches), enable the publisher and cut over
  one city at a time behind a feature flag.


---

## 8. Wave 3 analytics (optional profile)

```bash
docker compose -f docker-compose.prod.yml --profile analytics up -d clickhouse
ANALYTICS_ENABLED=true docker compose -f docker-compose.prod.yml up -d catalog-service scheduler
docker compose exec -T catalog-service php artisan analytics:project --limit=1000
docker compose exec -T clickhouse clickhouse-client --query \
  "SELECT event_type, count() FROM utterlazy.order_events GROUP BY event_type"
```

Rules:

- ClickHouse is not the source of truth; disabling the projector never affects orders.
- A failed insert is retried; no event is marked before ClickHouse accepts it.
- Do not point Command Center at ClickHouse until `city_daily` matches MySQL counts for a full day.


---

## 9. Wave 3 organic ranker (optional profile)

```bash
docker compose -f docker-compose.prod.yml --profile ranker up -d ranker
RANKER_ENABLED=true docker compose -f docker-compose.prod.yml up -d catalog-service
curl -fsS http://127.0.0.1:8010/health
curl -s "http://127.0.0.1:8001/api/recommendations/ranked-organic?lat=28.61&lng=77.21&limit=10"
```

Rules:

- Sponsored candidates are rejected by the ranker (HTTP 422); never bypass this.
- When disabled/unreachable, Laravel falls back to deterministic local ranking
  (`source: php_fallback`) — discovery keeps working.
- The endpoint is additive; existing recommendation APIs are unchanged.


---

## 10. Ops incidents

- Admin → Incident Console: open/acknowledge/resolve/assign.
- `incidents:sweep` runs every minute: at-risk (50% SLA, level 1) then breach (level 2),
  assigning a `support_lead` when unowned; each level emits once.
- Verify events: `docker compose exec -T catalog-service php artisan tinker` is not needed;
  query `outbox` for `ops_incident.%` or check the Kafka topic `utterlazy.ops.incident.v1`.
- Command Center shows open incidents, SEV1, and breach counts. A city row shows its open count.


---

## 11. PII audit verification

- Every admin export listed in `docs/wave4-pii-audit.md` must create one `pii_access_logs` row.
- Check: `GET /api/admin/pii-audit?action=export` (auditor token) shows the actor, filters,
  row count and timestamp.
- Automated: `php tests/wave1_smoke.php` covers append-only behaviour and role permissions.
- Do not add update/delete paths to `pii_access_logs`; retention changes require a dedicated
  audited command.


---

## 12. Ops shifts

- Admin → Shift Planner: schedule coverage by agent + city + org unit.
- Command Center shows agents on shift now (global and per city).
- Overlap and >12h shifts are rejected by the API; cancellations require a reason.
- If coverage is zero, that is an operational signal — open an incident rather than
  editing a past shift.


---

## 13. Safety SOS

- Set `SOS_PARTNER_WEBHOOK` for the insurance/emergency partner; leave empty to disable.
- Test: rider app SOS → `sos_alerts` row + SEV1 `safety` incident + `safety.sos_triggered`
  in `outbox`/Kafka. Partner receives the webhook (if configured).
- Repeated SOS within 5 minutes dedupes; ops ack/resolve from Incident Console.
- Never delete SOS rows; they are safety evidence.


---

## 14. Internal OTP (WhatsApp)

- WhatsApp service calls `/api/internal/otp/request|verify` with `X-Service-Token`.
- Limits are per phone, not per IP: 3/10min, 10/day, 5 attempts, 5-min TTL.
- Failed SMS delivery does not count against the throttle (row is deleted).
- Rolling deploy: if the internal route returns 404, WhatsApp falls back to the public endpoints.
- Verify with `php tests/wave1_smoke.php` (12 OTP checks) and a real phone before launch.


---

## 15. ONDC (disabled until certified)

- Keep `ONDC_ENABLED=false` until registry onboarding and signed callback transport exist.
- When testing search: set subscriber/key/private key + `ONDC_REGISTRY_KEYS`, enable, and send a
  signed request. Failures return `401 INVALID_SIGNATURE` and log the transaction id.
- `select`/`init` return quotes/terms only; no order is created. `confirm` stays NACKed.
- Outbound callbacks: set `ONDC_CALLBACK_ENABLED=true` only after `bap_uri` is verified and the
  queue worker is running. Each callback is freshly signed.
- `confirm` intentionally returns `501 ONDC_CONFIRM_NOT_ENABLED`. Do not bypass it; ONDC order
  creation requires payment/fulfilment/cancellation parity.


---

## 16. Ranker training (offline)

```bash
# export + train in one command
DAYS=30 ./scripts/ranker_train.sh
# inspect metrics
cat infra/ranker/model.json | head -40
```

- No production traffic uses the trained model; `ranking.py` still runs the deterministic
  weighted policy.
- Treat `infra/ranker/dataset.csv` as PII-adjacent (restaurant/order aggregates): restrict access
  and do not commit it.
- Before serving: time-travel features, offline evaluation vs the current policy, and an A/B gate.


---

## 17. Ranker A/B serving

- Default: policy only. Nothing changes without a mounted model and `RANKER_MODEL_ENABLED`.
- Load gate: exact feature list + test AUC ≥ 0.60 + accuracy ≥ 0.55; otherwise `policy_fallback`.
- Start in shadow (`RANKER_SHADOW_ENABLED=true`, model enabled false), compare shadow vs policy
  for a week, then ramp share 10 → 25 → 50 with guardrails.
- `assignment_key` must be a stable user id from the caller; without it the request stays policy.
- Kill switch: set `RANKER_MODEL_ENABLED=false` and restart the ranker (or remove the model mount).


---

## 18. Certification (final gate)

```bash
MODE=offline WITH_FRONTENDS=true ./scripts/certify.sh          # local/CI
MODE=staging SERVICE_TOKEN=... CITY_ID=... COMPOSE_FILE=docker-compose.prod.yml \
  ORDER_COMMAND_SHADOW_ENABLED=true RANKER_URL=http://127.0.0.1:8010 \
  ./scripts/certify.sh                                          # on deploy host
```

- Review `infra/certification/report-*.md`; resolve every SKIP before release.
- Non-zero exit = blocked release.
- Complete the human sign-off list in `docs/certification.md`.


---

## 19. PII retention

```bash
# dry-run first (default)
docker compose exec -T catalog-service php artisan pii:retention --days=180 --actor=auditor@utterlazy.com
# apply after review
docker compose exec -T catalog-service php artisan pii:retention --days=180 \
  --actor=auditor@utterlazy.com --apply --confirm=DELETE
```

- Minimum 30 days; without `--confirm=DELETE` the command refuses to delete.
- Every run is recorded in `pii_retention_runs` and visible in PII Audit → Retention runs.
- This command never touches SOS, incidents, orders, or audit trails.


---

## 20. Cutover readiness

```bash
docker compose exec -T catalog-service php artisan cutover:check
docker compose exec -T catalog-service php artisan cutover:check --strict
```

- All checks must pass before setting `ORDER_COMMAND_CUTOVER_READY=true`.
- Command Center shows READY/BLOCKED and the blocker count.
- Flags alone do not move traffic; checkout remains on Laravel until the proxy/projection work
  lands. Do not edit the gate to force READY.


---

## 21. Order projection (cutover prerequisite)

```bash
# dev/staging: Java publishes to Redis, Laravel projects
OUTBOX_ENABLED=true EVENT_BUS_DRIVER=redis docker compose --profile wave2 up -d order-command
docker compose --profile wave2 up -d projection-consumer
php artisan projection:status
```

- Projection writes `projected_orders` only; live orders are untouched.
- Duplicate events are deduped; stale versions cannot regress status.
- Production uses the Kafka source: `PROJECTION_TRANSPORT=kafka` (rdkafka in the image).
  Verify offsets advance with `php artisan projection:status`; keep Redis as the fallback.
- `cutover:check` blocks until projected orders exist in the last 24h.


---

## 22. Shift auto-rotation

```bash
# preview next Monday (default); no writes
php artisan shifts:rotate --city=<uuid> --hours=8 --per-day=1
# apply, recording the operator
php artisan shifts:rotate --city=<uuid> --hours=8 --per-day=1 --apply --actor=ops@utterlazy.com
```

- Adds only non-overlapping shifts; re-running is safe.
- Gap days are printed; use `--fail-on-gap` in automation to fail the job when coverage is short.
- Shift Planner UI: Preview/Apply + gap list.
- Applying with gaps creates owned SEV3 `staffing` incidents (deduped on re-apply).


---

## 23. Wave 2 hardening toggles

- **City capacity:** set `city_hourly_order_cap` (and `city_hourly_order_cap_<city-id>` overrides)
  in Global Config; 0 disables. Over-cap checkout returns 422 with no charge.
- **GPS anomalies:** implausible pings (>120 km/h) are rejected; 3 anomalies exclude the rider from
  assignment until a normal ping resets the counter.
- **Scheduled closures:** `scheduled-orders:cancel-closed` runs every 5 minutes for slots ≤60 min ahead.
- **Session revoke:** restaurant bank-detail changes and rider UPI changes invalidate tokens; the
  client must re-login.


---

## 24. OpenSearch organic search

```bash
docker compose -f docker-compose.prod.yml --profile search up -d opensearch
OPENSEARCH_ENABLED=true docker compose -f docker-compose.prod.yml up -d catalog-service scheduler
docker compose exec -T catalog-service php artisan search:index-restaurants
curl "http://127.0.0.1:8001/api/search/fast?q=biryani"
```

- Disabled/unreachable → MySQL search response (no hard failure).
- Daily bulk index at 04:00; run manually after large catalog changes.
- Single-node dev/staging is fine; size the cluster for production before enabling.


---

## 25. Dispatch service + tuning

```bash
./scripts/dispatch_core_test.sh
```

```bash
docker compose -f docker-compose.prod.yml --profile dispatch up -d dispatch-service
curl -s localhost:8004/api/dispatch/health
```

- Stateless assign/batch API; geohash stands in for H3.
- Do not route live orders to it until Redis GEO ingestion, offer timers and
  `dispatch.assignment.v1` are built and certified.

### Exploration tuning

```bash
php artisan riders:tune-exploration --days=7            # preview
php artisan riders:tune-exploration --days=7 --apply    # persist rider_exploration_every
```

### Ranker guardrails

```bash
python3 services/ranker/evaluate.py --csv infra/ranker/dataset.csv \
  --model infra/ranker/model.json --baseline infra/ranker/previous.json
```


---

## 26. Time-travel ranker features

```bash
php artisan ranker:export-clickhouse --as-of=2026-10-01T00:00:00Z --city=<uuid> \
  --out=infra/ranker/asof.csv
```

- Counts/GMV per restaurant up to the timestamp (ClickHouse `order_events`).
- Static attributes (rating, prep, price) still need a MySQL join before training.
- Requires the analytics profile/ClickHouse reachable.
