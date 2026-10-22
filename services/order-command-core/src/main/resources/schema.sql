-- order-command schema (MySQL 8 + H2 in MySQL mode).
-- Owned by the Java service. The Laravel `orders` table is untouched during shadow mode.

CREATE TABLE IF NOT EXISTS orders_v2 (
    id              VARCHAR(36) PRIMARY KEY,
    user_id         VARCHAR(36) NOT NULL,
    restaurant_id   VARCHAR(36) NOT NULL,
    city_id         VARCHAR(36),
    channel         VARCHAR(12) NOT NULL,
    status          VARCHAR(30) NOT NULL,
    cancel_reason   VARCHAR(40),
    grand_total     DECIMAL(12, 2) NOT NULL,
    tax_amount      DECIMAL(12, 2) NOT NULL DEFAULT 0,
    vendor_discount_amount DECIMAL(12, 2) NOT NULL DEFAULT 0,
    delivery_fee    DECIMAL(12, 2) NOT NULL DEFAULT 0,
    platform_fee    DECIMAL(12, 2) NOT NULL DEFAULT 0,
    coupon_discount_amount DECIMAL(12, 2) NOT NULL DEFAULT 0,
    commission      DECIMAL(12, 2) NOT NULL DEFAULT 0,
    commission_gst  DECIMAL(12, 2) NOT NULL DEFAULT 0,
    net_payout      DECIMAL(12, 2) NOT NULL DEFAULT 0,
    idempotency_key VARCHAR(80),
    version         BIGINT NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NOT NULL,
    updated_at      TIMESTAMP NOT NULL,
    CONSTRAINT ux_orders_v2_idem UNIQUE (user_id, idempotency_key)
);

CREATE INDEX ix_orders_v2_restaurant ON orders_v2 (restaurant_id, created_at);

CREATE TABLE IF NOT EXISTS order_lines_v2 (
    id           BIGINT AUTO_INCREMENT PRIMARY KEY,
    order_id     VARCHAR(36) NOT NULL,
    menu_item_id VARCHAR(36) NOT NULL,
    variant_id   VARCHAR(36),
    quantity     INT NOT NULL,
    unit_price   DECIMAL(12, 2) NOT NULL,
    line_total   DECIMAL(12, 2) NOT NULL,
    CONSTRAINT fk_order_lines_v2_order FOREIGN KEY (order_id) REFERENCES orders_v2 (id)
);

CREATE INDEX ix_order_lines_v2_order ON order_lines_v2 (order_id);

CREATE TABLE IF NOT EXISTS outbox_v2 (
    event_id       VARCHAR(36) PRIMARY KEY,
    aggregate_type VARCHAR(50) NOT NULL,
    aggregate_id   VARCHAR(36) NOT NULL,
    event_type     VARCHAR(80) NOT NULL,
    payload        TEXT NOT NULL,
    restaurant_id  VARCHAR(36),
    city_id        VARCHAR(36),
    occurred_at    TIMESTAMP NOT NULL,
    published_at   TIMESTAMP,
    attempts       INT NOT NULL DEFAULT 0,
    last_error     VARCHAR(1000)
);

CREATE INDEX ix_outbox_v2_pending ON outbox_v2 (published_at, occurred_at);
