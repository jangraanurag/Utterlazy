package com.utterlazy.order.domain;

import java.math.BigDecimal;
import java.time.Instant;
import java.util.ArrayList;
import java.util.Collections;
import java.util.List;
import java.util.Objects;
import java.util.UUID;

/**
 * Order aggregate root. All state changes go through the state machine and
 * emit domain events; there is no public setter.
 */
public final class Order {

    public static final int MIN_LINES = 1;
    public static final int MAX_LINES = 50;

    private final UUID id;
    private final UUID userId;
    private final UUID restaurantId;
    private final UUID cityId;
    private final OrderChannel channel;
    private final String idempotencyKey;
    private final List<OrderLine> lines;
    private final BigDecimal grandTotal;
    private final PricingBreakdown pricing;
    private final Instant createdAt;

    private OrderStatus status;
    private String cancelReason;
    private long version;
    private final List<DomainEvent> pendingEvents = new ArrayList<>();

    private Order(UUID id, UUID userId, UUID restaurantId, UUID cityId, OrderChannel channel,
                  String idempotencyKey, List<OrderLine> lines, BigDecimal grandTotal,
                  PricingBreakdown pricing, Instant createdAt) {
        this.id = id;
        this.userId = userId;
        this.restaurantId = restaurantId;
        this.cityId = cityId;
        this.channel = channel;
        this.idempotencyKey = idempotencyKey;
        this.lines = List.copyOf(lines);
        this.grandTotal = grandTotal;
        this.pricing = pricing;
        this.createdAt = createdAt;
        this.status = OrderStatus.PENDING;
    }

    public static Order place(UUID userId, UUID restaurantId, UUID cityId, OrderChannel channel,
                              String idempotencyKey, List<OrderLine> lines) {
        return place(userId, restaurantId, cityId, channel, idempotencyKey, lines, null);
    }

    public static Order place(UUID userId, UUID restaurantId, UUID cityId, OrderChannel channel,
                              String idempotencyKey, List<OrderLine> lines, PricingBreakdown pricing) {
        Objects.requireNonNull(userId, "userId");
        Objects.requireNonNull(restaurantId, "restaurantId");
        Objects.requireNonNull(channel, "channel");

        if (lines == null || lines.size() < MIN_LINES || lines.size() > MAX_LINES) {
            throw new IllegalArgumentException(
                    "order must contain between " + MIN_LINES + " and " + MAX_LINES + " lines");
        }
        if (channel == OrderChannel.WHATSAPP && (idempotencyKey == null || idempotencyKey.isBlank())) {
            throw new IllegalArgumentException("Idempotency-Key is required for WhatsApp orders");
        }
        if (idempotencyKey != null && idempotencyKey.length() > 80) {
            throw new IllegalArgumentException("Idempotency-Key must be <= 80 chars");
        }

        BigDecimal linesTotal = lines.stream()
                .map(OrderLine::total)
                .reduce(BigDecimal.ZERO, BigDecimal::add);

        BigDecimal total = pricing != null ? pricing.grandTotal() : linesTotal;

        if (total.signum() <= 0) {
            throw new IllegalArgumentException("order total must be > 0");
        }

        Order order = new Order(UUID.randomUUID(), userId, restaurantId, cityId,
                channel, idempotencyKey, lines, total, pricing, Instant.now());

        order.record("order.created", order.snapshot());
        return order;
    }

    /** Rehydrate from persistence (adapter use). */
    public static Order restore(UUID id, UUID userId, UUID restaurantId, UUID cityId,
                                OrderChannel channel, String idempotencyKey, List<OrderLine> lines,
                                BigDecimal grandTotal, PricingBreakdown pricing,
                                OrderStatus status, String cancelReason,
                                long version, Instant createdAt) {
        Order order = new Order(id, userId, restaurantId, cityId, channel, idempotencyKey,
                lines, grandTotal, pricing, createdAt);
        order.status = status;
        order.cancelReason = cancelReason;
        order.version = version;
        return order;
    }

    public void accept() {
        transition(OrderStatus.CONFIRMED);
    }

    public void startPreparing() {
        transition(OrderStatus.PREPARING);
    }

    public void markReady() {
        transition(OrderStatus.READY_FOR_PICKUP);
    }

    public void pickup() {
        transition(OrderStatus.OUT_FOR_DELIVERY);
    }

    public void deliver() {
        transition(OrderStatus.DELIVERED);
    }

    public void cancel(String reason) {
        if (reason == null || reason.isBlank()) {
            throw new IllegalArgumentException("cancel reason is required");
        }
        this.cancelReason = reason;
        transition(OrderStatus.CANCELLED);
    }

    public void abandon(String reason) {
        if (reason == null || reason.isBlank()) {
            throw new IllegalArgumentException("abandon reason is required");
        }
        this.cancelReason = reason;
        transition(OrderStatus.ABANDONED);
    }

    public void rescue() {
        transition(OrderStatus.RESCUED);
    }

    private void transition(OrderStatus target) {
        if (!status.canTransitionTo(target)) {
            throw new InvalidTransitionException(status, target);
        }
        OrderStatus from = status;
        status = target;
        version++;

        record("order.status_changed", snapshotWithFrom(from));
    }

    private void record(String type, Object data) {
        pendingEvents.add(DomainEvent.of(type, id.toString(), restaurantId.toString(), data));
    }

    private OrderSnapshot snapshot() {
        return new OrderSnapshot(id, userId, restaurantId, cityId, channel, status, grandTotal,
                pricing, cancelReason, version);
    }

    private OrderSnapshot snapshotWithFrom(OrderStatus from) {
        return new OrderSnapshot(id, userId, restaurantId, cityId, channel, status, grandTotal,
                pricing, cancelReason, version, from);
    }

    public List<DomainEvent> pullEvents() {
        List<DomainEvent> events = List.copyOf(pendingEvents);
        pendingEvents.clear();
        return events;
    }

    public boolean isTerminal() {
        return status.isTerminal();
    }

    public UUID id() { return id; }
    public UUID userId() { return userId; }
    public UUID restaurantId() { return restaurantId; }
    public UUID cityId() { return cityId; }
    public OrderChannel channel() { return channel; }
    public String idempotencyKey() { return idempotencyKey; }
    public List<OrderLine> lines() { return Collections.unmodifiableList(lines); }
    public BigDecimal grandTotal() { return grandTotal; }
    public PricingBreakdown pricing() { return pricing; }
    public OrderStatus status() { return status; }
    public String cancelReason() { return cancelReason; }
    public long version() { return version; }
    public Instant createdAt() { return createdAt; }

    /** Serialisable projection carried by events. */
    public record OrderSnapshot(
            UUID orderId,
            UUID userId,
            UUID restaurantId,
            UUID cityId,
            OrderChannel channel,
            OrderStatus status,
            BigDecimal grandTotal,
            PricingBreakdown pricing,
            String cancelReason,
            long version,
            OrderStatus fromStatus
    ) {
        public OrderSnapshot(UUID orderId, UUID userId, UUID restaurantId, UUID cityId,
                             OrderChannel channel, OrderStatus status, BigDecimal grandTotal,
                             PricingBreakdown pricing, String cancelReason, long version) {
            this(orderId, userId, restaurantId, cityId, channel, status, grandTotal, pricing,
                    cancelReason, version, null);
        }
    }
}
