# 🔭 UtterLazy v2 — High-Level Design (HLD)

Target state components, data flows, and deployment. Implementation detail lives in `docs/lld.md`.

---

## 1. Components

| Component | Tech | Deployment | Scale notes |
|---|---|---|---|
| nginx | — | 1 host (today), LB later | routes utterlazy.com / admin. / vendor. / api. / catalog. / ws. |
| api-gateway | Laravel 13 | container `utterlazy-api-gateway` :8000 | auth, FCM, asset proxy, BFF rate limits |
| catalog-service | Laravel 13 | container :8001 | modular monolith — orders, catalog, finance, approvals |
| queue-worker | Laravel | container | drains Redis queues |
| scheduler | Laravel | container, sole runner | cron loop |
| reverb | Laravel Reverb | container :8081→8080 | KDS + tracking websockets |
| mysql | MySQL 8 | container | catalog DB. Orders split to own DB in Wave 2 (shard `city_id`) |
| redis | Redis 7 | container | cache, queue, geo (Wave 2), dev-stream transport |
| kafka | KRaft 3 brokers | containers | fact log, partition key `city_id` where available (order/payment events: `restaurant_id`) |
| whatsapp-service | Laravel 13 | new container + own MySQL schema `food_whatsapp` | Meta Cloud API behind `WhatsAppProvider` |
| order-command / dispatch | Java Spring Boot | Wave 2 | hot path extraction |
| ranker / analytics | Python / ClickHouse | Wave 3 | organic rank + Command Center data |

## 2. Flow — order placed (all channels)

```
Customer (app|web|whatsapp)
   → api-gateway (auth)
   → catalog-service POST /checkout  [+ channel, Idempotency-Key]
        │ validate: restaurant open, radius ≤10km, lines, stock, discounts
        │ DB tx: order row + outbox row (order.events.v1: order.created)
        ▼
   Kafka order.events.v1
        ├── whatsapp-service → status template (utility consent)
        ├── notification worker → FCM to vendor (prepaid: only after payment.events)
        └── (Wave 2) order-command owns this write path
```

## 3. Flow — payment

```
Razorpay verify (prepaid) / COD confirm
   → catalog PaymentController
        DB tx: payment_status + outbox (payment.events.v1)
   → Kafka
        ├── vendor notified (NewOrderForVendor) for prepaid
        └── whatsapp-service sends receipt/status template
```

## 4. Flow — WhatsApp conversation

```
Meta webhook POST /webhook/meta
   → signature verify → dedupe by wa_message_id
   → StateMachine.resolve(state, message)
   → IntentRouter (Gemini, tool schema; <0.75 → button menus)
        intents: search | add_item | track | cancel_request | complaint | human
   → outbound via WhatsAppProvider (Meta Cloud API)
        Kafka whatsapp.outbound.v1 (ack + analytics)
```

Cart/checkout: whatsapp-service holds conversation cart (`wa_carts`), then calls catalog checkout with `channel=whatsapp` + `Idempotency-Key`. It never writes `orders` directly.

## 5. Flow — paid WhatsApp recommendations

```
Restaurant buys placement (vendor-web WhatsApp Promotions)
   → POST /api/wa-placements (status draft)
   → AccountManagementApprovalBridge.originate (entity wa_placement)
   → approval act → finalizer → status active, pinned_floor copied
   → Kafka ads.placement.v1
```

Reco serve:
```
whatsapp-service GET /api/internal/wa-reco?city_id
   → catalog: placements WHERE status=active AND city AND budget remains AND pinned_floor ≥ bid
   → sort bid desc, limit max_slots, label Sponsored
   → Kafka ads.impression.v1; tap → ads.click.v1
```

Organic `NEAR_ME`/name search: separate catalog query, no placement filter on rank, sponsored flag never used as rank input.

## 6. Operations loop

```
Command Center (city rows): unaccepted>3min · ready-no-rider>8min · payment failures
   · whatsapp send failures · kitchen-full · COD outstanding
   → each row has owner role (city_ops / support_lead / whatsapp_ops)
   → unowned 2 min → page lead + backup on org chart
   → action is instant (reassign, retry template, supply hold) — no money moved
```

## 7. Deployment (Wave 1 delta)

```
docker-compose.yml:
  + kafka (bitnami/kafka KRaft, single-node dev; 3-broker in prod compose)
  + whatsapp-service  (build ./whatsapp-service, own DB schema)
  + outbox-publisher  (command php artisan outbox:publish --daemon, catalog image)
  existing: mysql, redis, api-gateway, catalog-service, queue-worker, scheduler, reverb, nginx
```

Config keys (catalog env):
- `KAFKA_BROKERS`, `KAFKA_TOPIC_PREFIX` (default `utterlazy.`), `KAFKA_SASL_*` (prod)
- `OUTBOX_TRANSPORT=kafka|redis` (dev default `redis`, prod `kafka`)
- `OUTBOX_BATCH_SIZE=100`, `OUTBOX_POLL_SECONDS=1`, `OUTBOX_MAX_RETRIES=5`

## 8. Failure handling

| Failure | Behavior |
|---|---|
| Outbox row written, publisher dies | row remains `published_at=NULL`, picked on restart |
| Broker ack lost | at-least-once; consumers dedupe on `event_id` |
| Consumer crash mid-batch | `processed_events` insert first → idempotent replay |
| 5 publish retries | topic DLQ + log; ops console surfaces lag |
| Meta webhook replay | dedupe by `wa_message_id` |
| Razorpay double verify | idempotent by `razorpay_payment_id` |
| WhatsApp session expired | utility template only; no free-form outside 24h window |

## 9. Security

- Webhook signature verification mandatory on POST.
- Internal reco API requires service token, not a user token.
- No phone numbers in WhatsApp messages; calls via EnableX masking.
- Staff deactivate never deletes audit rows. `auditor` is read-only.
- City scoping from `OrgUnit` — a Delhi agent cannot open a Jaipur record.

## 10. Observability

- Every event carries `event_id` + `occurred_at`; Kafka consumer lag on Command Center.
- Outbox lag (oldest `published_at=NULL` row age) in health check.
- Wave 3: ClickHouse replaces MySQL for Command Center analytics.
