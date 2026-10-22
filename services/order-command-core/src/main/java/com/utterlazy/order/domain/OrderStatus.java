package com.utterlazy.order.domain;

import java.util.EnumSet;
import java.util.Map;
import java.util.Set;

/**
 * Canonical order lifecycle. Mirrors the statuses already stored by
 * catalog-service so channel parity holds when the write path is extracted.
 */
public enum OrderStatus {
    PENDING,
    CONFIRMED,
    PREPARING,
    READY_FOR_PICKUP,
    OUT_FOR_DELIVERY,
    DELIVERED,
    CANCELLED,
    ABANDONED,
    RESCUED;

    private static final Map<OrderStatus, Set<OrderStatus>> ALLOWED = Map.of(
            PENDING, EnumSet.of(CONFIRMED, PREPARING, CANCELLED),
            CONFIRMED, EnumSet.of(PREPARING, CANCELLED),
            PREPARING, EnumSet.of(READY_FOR_PICKUP, CANCELLED),
            READY_FOR_PICKUP, EnumSet.of(OUT_FOR_DELIVERY, CANCELLED),
            OUT_FOR_DELIVERY, EnumSet.of(DELIVERED, ABANDONED, RESCUED),
            DELIVERED, EnumSet.noneOf(OrderStatus.class),
            CANCELLED, EnumSet.noneOf(OrderStatus.class),
            ABANDONED, EnumSet.noneOf(OrderStatus.class),
            RESCUED, EnumSet.noneOf(OrderStatus.class)
    );

    public boolean canTransitionTo(OrderStatus target) {
        return ALLOWED.getOrDefault(this, Set.of()).contains(target);
    }

    public boolean isTerminal() {
        return this == DELIVERED || this == CANCELLED || this == ABANDONED || this == RESCUED;
    }
}
