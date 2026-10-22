# 🏗️ UtterLazy v2 — Target Architecture (Approved Blueprint)

> "Har kisi ko uska hissa mile, koi chhoote nahi, koi suffer na kare."
> Strangler-fig evolution of the existing stack. No big-bang rewrite.

**Status:** Approved plan. Wave 1 is the first build. Waves 2–4 are sequenced, not implied.
**Last updated:** 21 Oct 2026

---

## 1. Scale Call (CEO-level honesty)

- Engineering SLO: **10M orders/day national**, city cell **50,000 orders/hour**.
- India has ~300–500k delivery-relevant kitchens. Data model supports millions of outlets.
- "Billions of orders/day" = ~1000x global food delivery. It is a shard-path direction, never a launch claim.
- Swiggy/Zomato each do ~2–3M orders/day. We target 3–5x with cells + shards.

Scale path (capability, not a claim): [`scale-path.md`](scale-path.md).

## 2. Non-negotiables

- Pure aggregator. Razorpay (`prepaid`) + COD + wallet. No BNPL, no postpaid.
- Same price, same order, same status on app / web / WhatsApp.
- Unpaid restaurants are never hidden. Paid partners get a small, disclosed ranking preference and a Sponsored label.
- Formula money moves automatically (commission, ₹/km, wait pay, placement spend).
- Discretionary money goes through the Org Approval Engine. Super admin cannot bypass.
- Rescue that does not move money is instant (city ops, minutes).
- AI may compose and suggest an order. Placement requires the customer's 4-digit WhatsApp PIN. AI never cancels or refunds.
- A city with zero online riders does not accept orders.

## 3. Runtime (target)

```
 Apps / Web / WhatsApp
        │
   api-gateway :8000          auth, rate limit, proxy
        │
   catalog-service :8001      orders, catalog, finance, approvals (modular monolith, extracted over time)
        │  same DB tx
   outbox ──OutboxPublisher──▶ Kafka (KRaft, 3 brokers)
        │
        ├── whatsapp-service   (new Laravel, own DB food_whatsapp)
        ├── catalog workers    (consumers: notifications, whatsapp ops events)
        └── admin-web / vendor-web
```

**Coupling rule:** services share no tables. They share versioned events only.

## 4. Kafka

- KRaft mode, 3 brokers. JSON Schema on the envelope. Per-topic DLQ.
- Envelope: `event_id`, `event_type`, `occurred_at`, `city_id`, `data`. Partition key `city_id` where available; order/payment events partition by `restaurant_id` (orders have no city column until Wave 2).
- Dedupe: consumers insert into `processed_events` first. Duplicate `event_id` = no-op.

| Topic | Producer | Notes |
|---|---|---|
| `catalog.restaurant.v1` | catalog | later |
| `catalog.menu.v1` | catalog | later |
| `catalog.availability.v1` | catalog | later |
| `order.events.v1` | catalog | create + every status change |
| `payment.events.v1` | catalog | verify + COD confirm + refund |
| `whatsapp.inbound.v1` | whatsapp-service | signed webhook |
| `whatsapp.outbound.v1` | whatsapp-service | provider acks |
| `ads.placement.v1` | catalog | approve/pause/exhaust/reject |
| `ads.impression.v1` | whatsapp-service | reco served |
| `ads.click.v1` | whatsapp-service | reco tapped |
| `support.ticket.v1` | whatsapp-service | human handoff |
| `dispatch.assignment.v1` | Java (Wave 2) | declared now |
| `rider.location.v1` | Java (Wave 2) | declared now, short retention |

## 5. Service map

| Service | Tech | Wave | Role |
|---|---|---|---|
| api-gateway | Laravel 13 | existing | Auth, FCM, proxy |
| catalog-service | Laravel 13 | existing | Orders, catalog, finance, approvals |
| whatsapp-service | Laravel 13 | **1** | Meta webhook, conversation FSM, paid reco |
| order-command | Java Spring Boot | 2 | Sole write path, saga, idempotency |
| dispatch | Java | 2 | H3 / Redis GEO rider assignment |
| search | OpenSearch | 2 | Organic restaurant/dish search |
| ranker | Python FastAPI | 3 | Organic app/web rank with a small disclosed paid preference; unpaid always visible |
| analytics | ClickHouse | 3 | Ads, SLA, Command Center |
| notifications | Laravel workers | 1 | FCM/SMS/WhatsApp sends from events |

## 6. Event backbone (Wave 1, catalog side)

- Transactional outbox: `outbox` table, written in the same DB transaction as the business write.
- `OutboxPublisher` worker marks `published_at` after broker ack. 5 failures → DLQ topic.
- Transport interface: `KafkaTransport` (php-rdkafka, prod) / `RedisTransport` (XADD stream, dev).

## 7. Order contract

- `orders.channel`: `app | web | whatsapp`, default `app`.
- Statuses (existing, unchanged): `pending → confirmed → preparing → ready_for_pickup → out_for_delivery → delivered`, side paths `cancelled`, `abandoned`, `rescued`.
- `Idempotency-Key` header required when `channel=whatsapp`. Replay returns stored order, no second row.
- WhatsApp calls the same `OrderController::checkout`. No second price, no second menu.
- Payment modes WhatsApp: `prepaid` (Razorpay link) and `cod` only. Wallet stays on the app.

## 8. WhatsApp product rule (paid reco only)

- `NEAR_ME` and name search: **organic** — every open restaurant inside 10 km.
- Recommendation carousels + proactive templates: paid partners first (labeled "Sponsored"), unpaid restaurants fill the remaining slots; marketing opt-in required.
- Ranking inside the carousel = CPC bid desc, cap = city `max_slots`. It is an auction, not a model.
- No active placement → restaurant never appears in a reco payload.
- Utility and marketing consents stored separately. Opt-out stops marketing, never order status.

## 9. Roles (target)

Existing stay: `super_admin`, `city_manager`, `support_agent`, `finance_manager`, `account_manager`.
Added: `support_l1`, `support_l2`, `support_lead`, `city_ops`, `rider_ops`, `catalog_ops`,
`onboarding_agent`, `ads_ops`, `whatsapp_ops`, `fraud_analyst`, `growth`, `auditor`.
Geography scoping via existing `OrgUnit`. Super admin has no money bypass.

## 10. Waves

| Wave | Scope | Gate |
|---|---|---|
| **1** | Kafka + outbox, idempotency + channel, whatsapp-service, placements + floors, 6 admin screens, new roles | Proof tests pass |
| **1b** | Nobody-suffers pack: auto-cancel reversal, cancel_reason, wait pay, COD cap, POD dispute rule, ready-no-rider clocks, checkout rider-supply check, Command Center exception rows | Before city scale |
| **2** | Java order-command + dispatch, orders DB shard by city_id, OpenSearch, early-dispatch platform wait pay, fake-GPS hold, festival caps, session revoke on bank/KYC change | Events stable in prod |
| **3** | Python ranker (organic only), ClickHouse, ONDC adapter per city, rider exploration slots from data, incident console | Wave 2 stable |
| **4** | Shifts on org units, PII export log, `safety.sos.v1` insurance hook | Audit-ready |

## 11. Never

- Billion-order launch claim. Hiding unpaid restaurants from search.
- AI placing/cancelling without the customer's 4-digit PIN. Super-admin money bypass.
- BNPL, postpaid, new wallet. Flutter rewrite. Fines without a formula.

## 12. Referenced docs

- `docs/hld.md` — components, flows, deployment.
- `docs/lld.md` — schemas, APIs, validations, files.
- `docs/build-checklist.md` — master checkbox list.
- `prd.md`, `architecture.md`, `design.md`, `memory.md` — existing context.
