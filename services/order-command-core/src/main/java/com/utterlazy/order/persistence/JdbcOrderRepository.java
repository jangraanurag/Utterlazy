package com.utterlazy.order.persistence;

import com.utterlazy.order.domain.Order;
import com.utterlazy.order.domain.OrderChannel;
import com.utterlazy.order.domain.OrderLine;
import com.utterlazy.order.domain.OrderStatus;
import com.utterlazy.order.domain.PricingBreakdown;
import com.utterlazy.order.port.OrderRepository;
import org.springframework.dao.OptimisticLockingFailureException;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.jdbc.core.RowMapper;

import java.math.BigDecimal;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Timestamp;
import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;

/**
 * JDBC adapter for the order aggregate. Lines are immutable after placement.
 * Updates use optimistic locking on {@code version}.
 */
public class JdbcOrderRepository implements OrderRepository {

    private static final RowMapper<OrderRow> ROW_MAPPER = (rs, i) -> new OrderRow(
            UUID.fromString(rs.getString("id")),
            UUID.fromString(rs.getString("user_id")),
            UUID.fromString(rs.getString("restaurant_id")),
            rs.getString("city_id") == null ? null : UUID.fromString(rs.getString("city_id")),
            OrderChannel.valueOf(rs.getString("channel")),
            rs.getString("idempotency_key"),
            rs.getBigDecimal("grand_total"),
            new PricingBreakdown(
                    rs.getBigDecimal("grand_total"),
                    rs.getBigDecimal("tax_amount"),
                    rs.getBigDecimal("vendor_discount_amount"),
                    rs.getBigDecimal("delivery_fee"),
                    rs.getBigDecimal("platform_fee"),
                    rs.getBigDecimal("coupon_discount_amount"),
                    rs.getBigDecimal("commission"),
                    rs.getBigDecimal("commission_gst"),
                    rs.getBigDecimal("net_payout")),
            OrderStatus.valueOf(rs.getString("status")),
            rs.getString("cancel_reason"),
            rs.getLong("version"),
            rs.getTimestamp("created_at").toInstant()
    );

    private final JdbcTemplate jdbc;

    public JdbcOrderRepository(JdbcTemplate jdbc) {
        this.jdbc = jdbc;
    }

    @Override
    public Optional<Order> findById(UUID id) {
        List<OrderRow> rows = jdbc.query(
                "SELECT * FROM orders_v2 WHERE id = ?", ROW_MAPPER, id.toString());

        return rows.stream().findFirst().map(this::rehydrate);
    }

    @Override
    public Optional<Order> findByIdempotencyKey(UUID userId, String idempotencyKey) {
        List<OrderRow> rows = jdbc.query(
                "SELECT * FROM orders_v2 WHERE user_id = ? AND idempotency_key = ?",
                ROW_MAPPER, userId.toString(), idempotencyKey);

        return rows.stream().findFirst().map(this::rehydrate);
    }

    @Override
    public void save(Order order) {
        Integer existing = jdbc.queryForObject(
                "SELECT COUNT(*) FROM orders_v2 WHERE id = ?", Integer.class, order.id().toString());

        if (existing == null || existing == 0) {
            insert(order);
            return;
        }

        update(order);
    }

    private void insert(Order order) {
        jdbc.update("""
                        INSERT INTO orders_v2
                        (id, user_id, restaurant_id, city_id, channel, status, cancel_reason,
                         grand_total, tax_amount, vendor_discount_amount, delivery_fee,
                         platform_fee, coupon_discount_amount, commission, commission_gst,
                         net_payout, idempotency_key, version, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        """,
                order.id().toString(),
                order.userId().toString(),
                order.restaurantId().toString(),
                order.cityId() == null ? null : order.cityId().toString(),
                order.channel().name(),
                order.status().name(),
                order.cancelReason(),
                order.grandTotal(),
                money(order.pricing() == null ? null : order.pricing().tax()),
                money(order.pricing() == null ? null : order.pricing().vendorDiscount()),
                money(order.pricing() == null ? null : order.pricing().deliveryFee()),
                money(order.pricing() == null ? null : order.pricing().platformFee()),
                money(order.pricing() == null ? null : order.pricing().couponDiscount()),
                money(order.pricing() == null ? null : order.pricing().commission()),
                money(order.pricing() == null ? null : order.pricing().commissionGst()),
                money(order.pricing() == null ? null : order.pricing().netPayout()),
                order.idempotencyKey(),
                order.version(),
                Timestamp.from(order.createdAt()),
                Timestamp.from(Instant.now()));

        for (OrderLine line : order.lines()) {
            jdbc.update("""
                            INSERT INTO order_lines_v2
                            (order_id, menu_item_id, variant_id, quantity, unit_price, line_total)
                            VALUES (?, ?, ?, ?, ?, ?)
                            """,
                    order.id().toString(),
                    line.menuItemId(),
                    line.variantId(),
                    line.quantity(),
                    line.unitPrice(),
                    line.total());
        }
    }

    private void update(Order order) {
        long previousVersion = order.version() - 1;

        int rows = jdbc.update("""
                        UPDATE orders_v2
                        SET status = ?, cancel_reason = ?, version = ?, updated_at = ?
                        WHERE id = ? AND version = ?
                        """,
                order.status().name(),
                order.cancelReason(),
                order.version(),
                Timestamp.from(Instant.now()),
                order.id().toString(),
                previousVersion);

        if (rows == 0) {
            throw new OptimisticLockingFailureException(
                    "Order " + order.id() + " was modified concurrently (expected version "
                            + previousVersion + ")");
        }
    }

    private Order rehydrate(OrderRow row) {
        List<OrderLine> lines = jdbc.query("""
                        SELECT menu_item_id, variant_id, quantity, unit_price
                        FROM order_lines_v2 WHERE order_id = ? ORDER BY id
                        """,
                (rs, i) -> new OrderLine(
                        rs.getString("menu_item_id"),
                        rs.getString("variant_id"),
                        rs.getInt("quantity"),
                        rs.getBigDecimal("unit_price")),
                row.id().toString());

        return Order.restore(row.id(), row.userId(), row.restaurantId(), row.cityId(),
                row.channel(), row.idempotencyKey(), lines, row.grandTotal(), row.pricing(),
                row.status(), row.cancelReason(), row.version(), row.createdAt());
    }

    private BigDecimal money(BigDecimal value) {
        return value == null ? BigDecimal.ZERO : value;
    }

    private record OrderRow(
            UUID id,
            UUID userId,
            UUID restaurantId,
            UUID cityId,
            OrderChannel channel,
            String idempotencyKey,
            BigDecimal grandTotal,
            PricingBreakdown pricing,
            OrderStatus status,
            String cancelReason,
            long version,
            Instant createdAt
    ) {
    }
}
