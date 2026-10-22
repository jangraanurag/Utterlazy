package com.utterlazy.order.port;

import com.utterlazy.order.domain.DomainEvent;
import java.util.List;

/** Transactional outbox writer: implement in the same DB transaction as the order save. */
public interface OutboxWriter {
    void append(List<DomainEvent> events);
}
