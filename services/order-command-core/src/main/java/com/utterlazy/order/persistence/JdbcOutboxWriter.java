package com.utterlazy.order.persistence;

import com.fasterxml.jackson.core.JsonProcessingException;
import com.fasterxml.jackson.databind.ObjectMapper;
import com.utterlazy.order.domain.DomainEvent;
import com.utterlazy.order.domain.Order;
import com.utterlazy.order.port.OutboxWriter;
import org.springframework.jdbc.core.JdbcTemplate;

import java.sql.Timestamp;
import java.util.List;
import java.util.UUID;

/**
 * Writes the full event envelope into {@code outbox_v2}. Must run inside the
 * same transaction as the order save (guaranteed by {@link JdbcUnitOfWork}).
 */
public class JdbcOutboxWriter implements OutboxWriter {

    private final JdbcTemplate jdbc;
    private final ObjectMapper objectMapper;

    public JdbcOutboxWriter(JdbcTemplate jdbc, ObjectMapper objectMapper) {
        this.jdbc = jdbc;
        this.objectMapper = objectMapper;
    }

    @Override
    public void append(List<DomainEvent> events) {
        for (DomainEvent event : events) {
            UUID cityId = extractCityId(event);

            String envelope = toJson(new Envelope(
                    event.eventId(),
                    event.eventType(),
                    event.occurredAt().toString(),
                    cityId == null ? null : cityId.toString(),
                    event.data()
            ));

            jdbc.update("""
                            INSERT INTO outbox_v2
                            (event_id, aggregate_type, aggregate_id, event_type, payload,
                             restaurant_id, city_id, occurred_at, published_at, attempts)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, 0)
                            """,
                    event.eventId().toString(),
                    event.aggregateType(),
                    event.aggregateId(),
                    event.eventType(),
                    envelope,
                    event.restaurantId(),
                    cityId == null ? null : cityId.toString(),
                    Timestamp.from(event.occurredAt()));
        }
    }

    private UUID extractCityId(DomainEvent event) {
        if (event.data() instanceof Order.OrderSnapshot snapshot) {
            return snapshot.cityId();
        }

        return null;
    }

    private String toJson(Object value) {
        try {
            return objectMapper.writeValueAsString(value);
        } catch (JsonProcessingException e) {
            throw new IllegalStateException("Failed to serialize outbox event", e);
        }
    }

    private record Envelope(UUID event_id, String event_type, String occurred_at,
                            String city_id, Object data) {
    }
}
