# 🧰 Non-code setup guide (production)

Everything here is **configuration, accounts, and external approvals** — no application code
changes. Keep secrets out of git; they belong in `.env` on the host or in the super-admin
`SystemConfig` where noted.

---

## 0. Host prerequisites

- Docker Engine + Compose plugin, git, JDK 17 (optional for local Java tests), Maven (CI does it).
- Repo at `/root/utterlazy` (or your path), MySQL backup before first deploy.
- Domains: `utterlazy.com`, `admin.`, `vendor.`, `api.`, `catalog.`, `ws.`, `wa.`.
- TLS certificates for nginx, DNS A/AAAA records.
- Ports: 80/443 public; 8000–8004, 8123, 9092, 9200 internal/loopback only.

```bash
cp api-gateway/.env.example api-gateway/.env
cp catalog-service/.env.example catalog-service/.env
cp whatsapp-service/.env.example whatsapp-service/.env
```

---

## 1. Secrets matrix

| Env var | Where to get it | Used by |
|---|---|---|
| `DB_PASSWORD` | choose/secret manager | MySQL + all services |
| `APP_KEY` (Laravel) | `php artisan key:generate` | gateway/catalog |
| `WA_APP_KEY` | `php artisan key:generate` (whatsapp-service) | whatsapp |
| `SERVICE_TOKEN` | `openssl rand -hex 32` (same on both) | catalog ↔ whatsapp/internal APIs |
| `RAZORPAY_KEY_ID`, `RAZORPAY_KEY_SECRET` | Razorpay dashboard | payments/refunds |
| `META_PHONE_NUMBER_ID`, `META_ACCESS_TOKEN`, `META_APP_SECRET`, `META_VERIFY_TOKEN` | Meta app dashboard | WhatsApp |
| `GEMINI_API_KEY` | Google AI Studio | NLP/voice/menu photo |
| `OLA_MAPS_API_KEY` | Ola Maps | routing/geocoding |
| `KAFKA_BROKERS` | internal `kafka-1:9092,...` | outbox/projection |
| `CLICKHOUSE_*` | internal clickhouse | analytics |
| `OPENSEARCH_*` | internal opensearch | search |
| `RANKER_*` | internal ranker | ranking |
| `ONDC_*` | ONDC registry/network | ONDC adapter |
| `SOS_PARTNER_WEBHOOK` | insurance/emergency partner | SOS hook |
| `FCM_*` | Firebase project | push |

Rules:

- Never commit `.env`, `memory.md`, service-account JSONs, or model artifacts.
- Rotate keys after any exposure; use different tokens per environment.
- `SERVICE_TOKEN` must be identical on catalog and whatsapp-service.

---

## 2. Meta WhatsApp setup

1. Create a Meta app → add **WhatsApp** product → get a phone number ID and permanent token.
2. Set the webhook URL: `https://wa.utterlazy.com/api/webhook/meta`
   (or `https://<host>:8002/api/webhook/meta` behind nginx).
3. Verify token = `META_VERIFY_TOKEN`.
4. Subscribe to `messages` and `message_status`.
5. Approve utility templates (default names in code):
   - `order_status_update` (params: order id, status)
   - `order_delivered`
   - `order_cancelled`
   - `payment_received`
6. Marketing/recommendation templates need their own approval and explicit opt-in.
7. Set `WA_PAYMENT_LINK_BASE=https://utterlazy.com/pay` for prepaid links.
8. Ensure the WhatsApp service can reach `catalog-service` with `SERVICE_TOKEN`.

Flow notes:

- First order: phone OTP link → 4-digit order PIN setup → order → payment link.
- Paid placements are shown first (`Sponsored`), organic restaurants fill the remaining slots.
- Proactive recommendations require marketing consent.

---

## 3. Razorpay setup

1. Create a Razorpay account → generate API keys (`key_id`, `key_secret`).
2. Enable the payment methods you support; UPI/cards as per policy.
3. Webhook URL: `https://catalog.utterlazy.com/api/payments/webhook`
   (confirm the exact route in `routes/api.php` for your build).
4. Subscribe to `payment.captured`, `payment.failed`, `refund.processed`.
5. Set the webhook secret in Razorpay and `RAZORPAY_WEBHOOK_SECRET` if your config uses it.
6. Verify a ₹1 sandbox order + refund before launch.

---

## 4. ONDC onboarding (disabled until certified)

1. Register the seller (BPP) on the ONDC network; obtain `subscriber_id`, `key_id`, `bpp_id`,
   `bpp_uri`.
2. Generate an Ed25519 keypair; register the public key with the registry.
3. Set:
   ```bash
   ONDC_ENABLED=false                 # keep false until certified
   ONDC_REQUIRE_SIGNATURE=true
   ONDC_SUBSCRIBER_ID=...
   ONDC_KEY_ID=...
   ONDC_PRIVATE_KEY=<base64 secret>
   ONDC_BPP_ID=...
   ONDC_BPP_URI=https://seller.example/ondc
   ONDC_REGISTRY_KEYS={"subscriber|key|ed25519":"<base64 public key>"}
   ONDC_CALLBACK_ENABLED=false
   ```
4. `search`, `select`, `init` are available (signed, no order writes).
5. `confirm` is intentionally **NACKed** until payment/fulfilment/RTO parity is certified.
6. Only enable callbacks after the BAP URL is verified and the queue worker runs.

---

## 5. Kafka / event backbone

- Prod compose runs 3 KRaft brokers (`kafka-1..3`), replication 3, min ISR 2.
- Topics are auto-created on first publish; verify:
  ```bash
  docker compose -f docker-compose.prod.yml exec kafka-1 \
    kafka-topics.sh --bootstrap-server kafka-1:9092 --list
  ```
- Outbox publisher: `OUTBOX_TRANSPORT=kafka`, `OUTBOX_ENABLED` worker running.
- Projection consumer: `PROJECTION_TRANSPORT=kafka`, group `utterlazy-order-projection`.
- Java order-command shadow: keep `OUTBOX_ENABLED=false` until parity; projection cert uses Redis.
- Rebuild the catalog image once to include **php-rdkafka** before enabling Kafka projection.

---

## 6. Analytics / search / ranker profiles

```bash
# ClickHouse
docker compose -f docker-compose.prod.yml --profile analytics up -d clickhouse
ANALYTICS_ENABLED=true ANALYTICS_COMMAND_CENTER_SOURCE=clickhouse \
  docker compose -f docker-compose.prod.yml up -d catalog-service scheduler

# OpenSearch
docker compose -f docker-compose.prod.yml --profile search up -d opensearch
OPENSEARCH_ENABLED=true docker compose -f docker-compose.prod.yml up -d catalog-service scheduler
docker compose exec -T catalog-service php artisan search:index-restaurants

# Ranker (starts in policy mode)
docker compose -f docker-compose.prod.yml --profile ranker up -d ranker
RANKER_ENABLED=true docker compose -f docker-compose.prod.yml up -d catalog-service
```

Ranker model activation (after training + guardrails):

```bash
# mount model read-only, then
RANKER_MODEL_ENABLED=false RANKER_SHADOW_ENABLED=true ...   # shadow first
RANKER_MODEL_SHARE_PERCENT=10 ...                           # then ramp
```

---

## 7. Super-admin dynamic controls (no deploy needed)

These are in **Admin → Global Config** (`SystemConfig`) or admin screens:

| Key / screen | Purpose |
|---|---|
| `city_hourly_order_cap`, `city_hourly_order_cap_<city_id>` | city/holiday capacity caps (0 = off) |
| `rider_exploration_every` | new-rider exploration frequency (tool: `riders:tune-exploration`) |
| delivery fee / platform fee / commission configs | pricing knobs |
| `require_pod_on_delivery` | POD enforcement |
| `block_checkout_without_riders` | supply gate |
| `membership_enabled` | membership toggle |
| Staff Provision | users + roles (support_l1/l2/lead, city_ops, rider_ops, ads_ops, whatsapp_ops, auditor, …) |
| Org Chart | geography-scoped units, roles, backups (on-call) |
| Approval Thresholds | DOA matrix; money cannot bypass |
| Shift Planner | roster, auto-rotation, gaps → SEV3 incidents |
| City Ad Floors / Placement Review | WhatsApp paid placement inventory |
| Command Center | live ops, incidents, SOS, projection, cutover gate |
| PII Audit | export log + retention runs |

Cutover flags (env, fail-closed):

```bash
ORDER_COMMAND_CUTOVER_ENABLED=true
ORDER_COMMAND_CUTOVER_READY=false        # true only after certification
ORDER_COMMAND_CITIES=<city-uuid,...>
ORDER_COMMAND_RESTAURANTS=<restaurant-uuid,...>
ORDER_COMMAND_SHADOW_ENABLED=true
CUTOVER_REQUIRE_CERTIFICATION=true
```

Run `php artisan cutover:check --strict` before flipping `READY`.

---

## 8. Deploy sequence (first production)

```bash
cd /root/utterlazy

# 1. build + data plane
docker compose -f docker-compose.prod.yml build catalog-service api-gateway \
  whatsapp-service outbox-publisher whatsapp-consumer
docker compose -f docker-compose.prod.yml up -d mysql redis kafka-1 kafka-2 kafka-3

# 2. migrations
docker compose -f docker-compose.prod.yml run --rm catalog-service php artisan migrate --force
docker compose -f docker-compose.prod.yml run --rm whatsapp-service php artisan migrate --force

# 3. app plane
docker compose -f docker-compose.prod.yml up -d

# 4. clear caches + health
docker compose exec -T catalog-service php artisan optimize:clear
curl -fsS http://127.0.0.1:8001/api/health
curl -fsS http://127.0.0.1:8002/api/health

# 5. verify + certify
SERVICE_TOKEN=... CITY_ID=... ./scripts/wave1_verify.sh
MODE=offline WITH_FRONTENDS=true ./scripts/certify.sh
```

Optional profiles: `analytics` (ClickHouse), `search` (OpenSearch), `ranker`, `dispatch`,
`wave2` (order-command shadow + projection consumer).

---

## 9. Go-live gates

- [ ] 48h soak: duplicate orders 0, DLQ empty, `refund_pending` < 1h old = 0
- [ ] Meta utility templates approved; opt-in language reviewed
- [ ] 7-day 100% pricing parity; `cutover:check --strict` all PASS
- [ ] 50k shadow replays with zero mismatches (if cutover is planned)
- [ ] Ranker shadow week → ramp 10/25/50 with guardrails
- [ ] SOS/insurance partner contract signed; webhook tested
- [ ] ONDC registry onboarding + callback review (confirm stays off)
- [ ] PII retention window legally approved
- [ ] Shift/on-call rota agreed; incident escalation tested
- [ ] Rollback drills: stop new services, flip cutover flags off, ranker kill switch

---

## 10. Rollback / kill switches

| Situation | Action |
|---|---|
| WhatsApp channel issue | stop `whatsapp-service` + `whatsapp-consumer`; catalog unaffected |
| Event bus issue | stop `outbox-publisher`; rows accumulate and publish later |
| Analytics/search/ranker issue | stop the profile or set `*_ENABLED=false`; MySQL/policy fallback |
| Bad model | `python3 services/ranker/promote.py --rollback --served model.json` |
| Cutover risk | set `ORDER_COMMAND_CUTOVER_ENABLED=false`, restart catalog |
| Suspicious staff session | Staff Provision → deactivate (tokens revoked); rotate `SERVICE_TOKEN` |
| Suspicious bank/UPI change | already revokes sessions; verify with finance |

---

## 11. Secret hygiene

- `.env`, `memory.md`, Firebase service accounts, APKs, build outputs, PII exports are gitignored.
- `memory.md` contains historical keys — rotate anything ever written there.
- Do not paste production tokens into chat/logs; use host env or a secret manager.
- After an accidental commit: rotate the secret first, then rewrite git history.
