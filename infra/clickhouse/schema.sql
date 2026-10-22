-- UtterLazy analytics schema (ClickHouse).
-- Event facts only. MySQL stays the operational source of truth.

CREATE DATABASE IF NOT EXISTS utterlazy;

-- Order lifecycle facts. ReplacingMergeTree dedupes on event_id.
CREATE TABLE IF NOT EXISTS utterlazy.order_events
(
    event_id        UUID,
    event_type      LowCardinality(String),
    occurred_at     DateTime64(3, 'Asia/Kolkata'),
    city_id         String,
    restaurant_id   String,
    order_id        String,
    user_id         String,
    channel         LowCardinality(String),
    status          LowCardinality(String),
    from_status     LowCardinality(String),
    payment_mode    LowCardinality(String),
    grand_total     Decimal(12, 2),
    ingested_at     DateTime DEFAULT now()
)
ENGINE = ReplacingMergeTree(ingested_at)
PARTITION BY toYYYYMM(occurred_at)
ORDER BY (city_id, occurred_at, order_id);

CREATE TABLE IF NOT EXISTS utterlazy.payment_events
(
    event_id        UUID,
    event_type      LowCardinality(String),
    occurred_at     DateTime64(3, 'Asia/Kolkata'),
    city_id         String,
    restaurant_id   String,
    order_id        String,
    user_id         String,
    payment_mode    LowCardinality(String),
    payment_status  LowCardinality(String),
    grand_total     Decimal(12, 2),
    ingested_at     DateTime DEFAULT now()
)
ENGINE = ReplacingMergeTree(ingested_at)
PARTITION BY toYYYYMM(occurred_at)
ORDER BY (city_id, occurred_at, order_id);

CREATE TABLE IF NOT EXISTS utterlazy.ad_events
(
    event_id        UUID,
    event_type      LowCardinality(String),
    occurred_at     DateTime64(3, 'Asia/Kolkata'),
    city_id         String,
    restaurant_id   String,
    placement_id    String,
    amount          Decimal(12, 2),
    ingested_at     DateTime DEFAULT now()
)
ENGINE = ReplacingMergeTree(ingested_at)
PARTITION BY toYYYYMM(occurred_at)
ORDER BY (city_id, occurred_at, placement_id);

-- Per-city daily rollup for Command Center charts.
CREATE TABLE IF NOT EXISTS utterlazy.city_daily
(
    day             Date,
    city_id         String,
    orders          UInt64,
    delivered       UInt64,
    cancelled       UInt64,
    no_rider        UInt64,
    refund_pending  UInt64,
    gmv             Decimal(14, 2)
)
ENGINE = SummingMergeTree
PARTITION BY toYYYYMM(day)
ORDER BY (day, city_id);

-- Daily city rollup fed by order_events. `refund_pending` stays 0 here;
-- finance refund state is operational (MySQL) until a payment MV is added.
CREATE MATERIALIZED VIEW IF NOT EXISTS utterlazy.city_daily_mv TO utterlazy.city_daily AS
SELECT
    toDate(occurred_at) AS day,
    city_id,
    countIf(event_type = 'order.created') AS orders,
    countIf(event_type = 'order.status_changed' AND status = 'delivered') AS delivered,
    countIf(event_type = 'order.status_changed' AND status = 'cancelled') AS cancelled,
    countIf(event_type = 'order.status_changed' AND status = 'cancelled' AND from_status = 'ready_for_pickup') AS no_rider,
    toUInt64(0) AS refund_pending,
    sumIf(grand_total, event_type = 'order.created') AS gmv
FROM utterlazy.order_events
GROUP BY day, city_id;

-- Backfill after enabling the MV on an existing install:
-- INSERT INTO utterlazy.city_daily
-- SELECT toDate(occurred_at) AS day, city_id,
--        countIf(event_type = 'order.created'),
--        countIf(event_type = 'order.status_changed' AND status = 'delivered'),
--        countIf(event_type = 'order.status_changed' AND status = 'cancelled'),
--        countIf(event_type = 'order.status_changed' AND status = 'cancelled' AND from_status = 'ready_for_pickup'),
--        toUInt64(0),
--        sumIf(grand_total, event_type = 'order.created')
-- FROM utterlazy.order_events GROUP BY day, city_id;
