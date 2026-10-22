package com.utterlazy.order.domain;

import java.time.Instant;
import java.util.Objects;
import java.util.UUID;

/** Versioned event envelope. Same shape as the Laravel outbox payload. */
public record DomainEvent(
        UUID eventId,
        String eventType,
        Instant occurredAt,
        String aggregateType,
        String aggregateId,
        String restaurantId,
        Object data
) {
    public static DomainEvent of(String eventType, String aggregateId, String restaurantId, Object data) {
        return new DomainEvent(
                UUID.randomUUID(),
                eventType,
                Instant.now(),
                "order",
                Objects.requireNonNull(aggregateId),
                restaurantId,
                data
        );
    }
}
