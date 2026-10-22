package com.utterlazy.order.port;

import com.utterlazy.order.domain.Order;
import java.util.Optional;
import java.util.UUID;

public interface OrderRepository {

    Optional<Order> findById(UUID id);

    Optional<Order> findByIdempotencyKey(UUID userId, String idempotencyKey);

    /** Persist with optimistic locking on {@code version}. */
    void save(Order order);
}
