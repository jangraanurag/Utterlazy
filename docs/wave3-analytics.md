# 📊 Wave 3 — Analytics read model (ClickHouse)

ClickHouse is the analytics store. MySQL remains the operational source of truth.
The projector is **disabled by default** and can never change a customer response.

## Components

| Piece | Status |
|---|---|
| `infra/clickhouse/schema.sql` — order/payment/ad facts + `city_daily` rollup | ✅ |
| `AnalyticsSink` port + `ClickHouseHttpSink` (JSONEachRow over HTTP) | ✅ |
| `AnalyticsProjector` — outbox → facts, idempotent | ✅ |
| `php artisan analytics:project` (scheduler every minute, no-op when off) | ✅ |
| `analytics_projected_events` dedupe table | ✅ |
| `--profile analytics` ClickHouse container (dev + prod) | ✅ |
| Command Center daily facts via ClickHouse with MySQL fallback | ✅ |
| Python organic ranker | ⬜ Wave 3 |
| ONDC adapter, incident console | ⬜ Wave 3 |

## Enable

```bash
docker compose -f docker-compose.prod.yml --profile analytics up -d clickhouse
# schema runs on first boot from infra/clickhouse/schema.sql

# In host env:
ANALYTICS_ENABLED=true
CLICKHOUSE_URL=http://clickhouse:8123
CLICKHOUSE_DATABASE=utterlazy
```

Restart `catalog-service` and `scheduler`. The scheduler runs `analytics:project --limit=200`
every minute; a failed insert is retried on the next pass.

## Guarantees

- One fact row per outbox event. `analytics_projected_events` prevents replays;
  `ReplacingMergeTree(ingested_at)` is the second safety net.
- An event is marked only **after** ClickHouse accepts the row.
- ClickHouse failure never blocks commerce: the projector logs and retries.
- Disabled config → the command exits before any query.

## Verification

```bash
docker compose exec -T catalog-service php artisan analytics:project --limit=1000

docker compose exec -T clickhouse clickhouse-client --query \
  "SELECT event_type, count() FROM utterlazy.order_events GROUP BY event_type"
docker compose exec -T clickhouse clickhouse-client --query \
  "SELECT city_id, sum(grand_total) FROM utterlazy.order_events GROUP BY city_id"
docker compose exec -T clickhouse clickhouse-client --query \
  "SELECT * FROM utterlazy.city_daily ORDER BY day DESC LIMIT 5"
```

Expected after one minute of traffic: order events match outbox counts; ad impressions/clicks
match `wa_placement_events`; no duplicate `event_id` after `OPTIMIZE ... FINAL`.

## Command Center source

Set `ANALYTICS_COMMAND_CENTER_SOURCE=clickhouse` (only meaningful with `ANALYTICS_ENABLED=true`).
Response includes `metrics_source`:

- `clickhouse` — daily city facts from `city_daily`
- `mysql` — source not enabled
- `mysql_fallback` — ClickHouse unreachable
- `mysql_fallback_empty` — ClickHouse reachable but no rows yet (day-one/backfill)

Live exception clocks (unaccepted, ready-no-rider, COD, refunds) stay on MySQL because they
need current state, not daily facts.

## Next slice

- Add SLA/refund materialized views.
- Compare `city_daily` with MySQL for a full day before removing the MySQL path.
- Only after parity: ClickHouse becomes the dashboard source, MySQL stays operational.
