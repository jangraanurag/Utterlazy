# 🔁 Wave 2.3 — Java order events → Laravel projection

Cutover cannot happen until Laravel can rebuild its world from Java's events. This slice builds
that projection pipeline **into a separate read model** (`projected_orders`), leaving the live
`orders` table untouched.

## Flow

```
Java order-command → outbox → Redis stream `outbox:order.events.v1`
    → php artisan projection:consume
    → processed_events (dedupe) + projected_orders (upsert by order_id, monotonic version)
```

Envelope: `event_id`, `event_type`, `occurred_at`, `city_id`, `data` where `data` is the Java
`OrderSnapshot` (`orderId`, `userId`, `restaurantId`, `cityId`, `channel`, `status`,
`grandTotal`, `pricing{...}`, `cancelReason`, `version`, `fromStatus`).

## Guarantees

- **Idempotent:** `(event_id, consumer)` claimed once in `processed_events`.
- **Monotonic:** an event with `version < stored version` is ignored (`stale_version`).
- **Transactional:** claim + upsert in one DB transaction; a failure rolls back and the cursor
  is not advanced.
- **No live interference:** writes only `projected_orders`; `orders`, payouts, notifications are
  untouched.

## Run (dev/staging, Redis transport)

```bash
# Java side (shadow profile): publish events
OUTBOX_ENABLED=true EVENT_BUS_DRIVER=redis docker compose --profile wave2 up -d order-command

# Laravel consumer
docker compose --profile wave2 up -d projection-consumer
# or: php artisan projection:consume --once
php artisan projection:status
curl /api/admin/projection/status   # admin.auth:orders.view
```

## Production transport (Kafka)

The catalog image now installs **php-rdkafka**. Consumers are pluggable:

```bash
# Redis (dev/staging)
php artisan projection:consume --transport=redis

# Kafka (production; Java publishes utterlazy.order.events.v1)
PROJECTION_TRANSPORT=kafka php artisan projection:consume --transport=kafka
```

Config: `PROJECTION_TRANSPORT`, `PROJECTION_KAFKA_TOPIC`, `PROJECTION_KAFKA_GROUP`,
`KAFKA_BROKERS`. Offsets are committed **only after** the projection writes; duplicates are
absorbed by `processed_events` (at-least-once). `projection-consumer` uses Kafka in prod compose.

> Rebuild the catalog image to pick up the rdkafka extension. Until the image is deployed,
> keep `PROJECTION_TRANSPORT=redis`.

## Cutover gate

`cutover:check` now requires **at least one projected order in 24h** (`projection_samples`).
The Command Center shows an **Order projection** card (24h count + total).

## Tests

13 checks in `tests/wave1_smoke.php`: created event, status change, duplicate event, stale
version (no regression), unsupported event, persisted pricing/channel/version, status summary.
Cutover gate tests include the projection blocker.

## Still required for cutover

- Deploy the rebuilt image and verify Kafka offsets advance (`projection:status`)
- Laravel downstream consumers moved to projected events (notifications, rider assignment,
  finance) or dual-run verified
- Java ownership of pricing/payments before traffic moves
- 7-day parity + certification
