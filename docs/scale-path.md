# 📈 Scale path — from city cells to a billion-order-capable architecture

**This is a capability path, not a launch claim or SLA.** The entire global food-delivery market
is on the order of tens of millions of orders/day. A billion/day is ~1,000× today's demand; we
design so the architecture *can* shard there, while the engineering SLO stays a measured
national peak (current target: 10M/day, city cell 50k orders/hour).

## Capacity math

| Volume | Average/s | Peak @3× | Peak @5× |
|---|---|---|---|
| 10M/day | 116 | 347 | 579 |
| 100M/day | 1,157 | 3,472 | 5,787 |
| 1B/day | 11,574 | 34,722 | 57,870 |

At 1B/day with a 40:1 read/write ratio and ~10 events per order, the event backbone needs
~460k events/s peak — that is a **cell + shard** problem, not a bigger server.

## Cell architecture

```
Region
 └── City cell (≈1–3M people)
      ├── order-command shard (city_id)
      ├── dispatch shard (H3 cells)
      ├── catalog/search read models
      ├── Kafka partitions keyed by city_id
      └── Redis (geo, carts, idempotency)
```

Rules:

- No cross-cell synchronous calls on the order path.
- Every write carries `city_id`; every topic partitions by it.
- A hot city is isolated: its load cannot exhaust another cell's consumers.
- Cells are identical; onboarding a city is configuration, not a new codebase.
- Cross-cell concerns (finance, approvals, identity) are async and idempotent.

## Shard plan

| Phase | Data | Trigger |
|---|---|---|
| 0 (now) | Single MySQL, outbox, Redis, Kafka 3-broker | current |
| 1 | Separate orders DB from catalog DB; read models (projection, ClickHouse, OpenSearch) | order writes contend with catalog |
| 2 | Orders sharded by `city_id` (city-group shards) | single orders DB > ~5k writes/s |
| 3 | Regional cells with independent Kafka/Redis/DB | region latency or blast-radius |
| 4 | Global cells + ONDC-style network adapters | multi-country |

## Component limits to watch

| Component | Practical unit | Scale action |
|---|---|---|
| MySQL orders | ~5–10k writes/s/shard | shard by city group; partition history |
| Kafka | partitions × ~10–50 MB/s/broker | 12–48 partitions/topic; 3+ brokers/cell |
| Redis | ~100k ops/s/node | cluster; geo shards per city |
| Outbox publisher | batch 200–1000 | one publisher per DB shard |
| Projection consumer | idempotent, offset-committed | scale by partition; one consumer group per shard |
| OpenSearch | 1 shard per ~20–50 GB | index per region; replicas for reads |
| ClickHouse | 1 shard per ~1B rows/day | city/month partitions; TTL hot data |
| Payment gateway | provider rate limits | queue + reconciliation, never inline retries |

## Required invariants at every phase

- Idempotency key on every command; unique `(user, key)`.
- Transactional outbox; consumers dedupe on `event_id`.
- Versioned events; monotonic order versions.
- Backpressure: bounded queues, 429 at the edge, no unbounded fan-out.
- Per-city quotas (`city_hourly_order_cap`) and supply gates.
- Read models are rebuildable from events; MySQL remains operational truth until cutover.
- Money reconciliation independent of the event bus.

## What we deliberately do not do

- Promise 1B/day as a launch SLA or market size.
- Multi-region active-active for order writes before shard 2 is proven.
- Cross-cell distributed transactions.
- Rewriting the stack for hypothetical volume before the measured national peak.

## Verification

- `docs/certification.md` gates (smoke, pricing, parity, projection, search, ranker).
- Load tests must be run per cell with realistic basket/read ratios before each shard phase.
- `cutover:check` must stay all-PASS per city; scale changes never bypass it.
