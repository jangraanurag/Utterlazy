# 🛵 Wave 2 — Dispatch core (groundwork)

Framework-free dispatch logic for the future Java dispatch service. Geohash is used as the
H3-equivalent cell key so the module has **zero native dependencies**; production can swap in H3
without changing the policy contract.

## What is built

| Piece | Behaviour |
|---|---|
| `GeoCell` | geohash encode (1–12), haversine distance, range validation |
| `RiderCandidate` | availability/verification, active load, acceptance, ping freshness, trips |
| `DispatchPolicy` | eligibility filters, distance/workload/acceptance/freshness scoring, new-rider exploration |
| `BatchPlanner` | greedy 1–3 order batches by pickup/drop proximity |
| Self-test | **20 checks** (`./scripts/dispatch_core_test.sh`) |

## Policy

- Exclude: offline, unverified, ping older than 30 min, more than 1 active order, pickup >6 km,
  and explicitly excluded riders.
- Score: distance 0.45, workload 0.20, acceptance 0.15, freshness 0.10, exploration 0.10.
- Exploration: riders with <20 trips get +0.05, and **every 8th assignment** prefers new riders
  when any are eligible (anti-starvation, never a penalty to seniors).
- Deterministic tie-break by `rider_id`.

## Batching

Pickups within `pickupRadiusKm` and drops within `dropRadiusKm` can share a rider,
up to 3 orders (configurable 1–3). Input order is preserved for determinism.

## Deliberately not built here

- Redis GEO/H3 storage and live rider location ingestion
- Kafka `dispatch.assignment.v1` producer/consumer
- offer timers, retries, surge integration, ETA/cost coupling
- Java Spring adapter and routing to actual riders

Those are the Wave 2 dispatch service; this module is the tested policy core it will call.

## Sharding groundwork

`order-command-core` now includes `ShardRouter` (SHA-256 city → shard), tested for determinism,
range, distribution and invalid input. It is the routing primitive for the future orders DB split;
no database is sharded yet.
