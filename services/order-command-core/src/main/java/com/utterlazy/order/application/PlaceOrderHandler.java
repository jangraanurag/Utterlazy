package com.utterlazy.order.application;

import com.utterlazy.order.domain.DomainEvent;
import com.utterlazy.order.domain.Order;
import com.utterlazy.order.port.OrderRepository;
import com.utterlazy.order.port.OutboxWriter;
import com.utterlazy.order.port.UnitOfWork;
import java.util.List;

/**
 * Place-order use case. Idempotency is enforced twice:
 * 1) a fast lookup by (user, key) returns the existing order;
 * 2) the adapter's unique constraint on (user_id, key) settles races — the
 *    duplicate insert fails and the handler re-reads the winner.
 */
public final class PlaceOrderHandler {

    private final OrderRepository orders;
    private final OutboxWriter outbox;
    private final UnitOfWork unitOfWork;

    public PlaceOrderHandler(OrderRepository orders, OutboxWriter outbox, UnitOfWork unitOfWork) {
        this.orders = orders;
        this.outbox = outbox;
        this.unitOfWork = unitOfWork;
    }

    public PlaceOrderResult handle(PlaceOrderCommand command) {
        if (command.idempotencyKey() != null && !command.idempotencyKey().isBlank()) {
            var existing = orders.findByIdempotencyKey(command.userId(), command.idempotencyKey());
            if (existing.isPresent()) {
                return new PlaceOrderResult(existing.get(), true);
            }
        }

        try {
            Order created = unitOfWork.inTransaction(() -> {
                Order order = Order.place(
                        command.userId(),
                        command.restaurantId(),
                        command.cityId(),
                        command.channel(),
                        command.idempotencyKey(),
                        command.lines(),
                        command.pricing()
                );

                orders.save(order);

                List<DomainEvent> events = order.pullEvents();
                outbox.append(events);

                return order;
            });

            return new PlaceOrderResult(created, false);
        } catch (RuntimeException race) {
            // Unique-key race: someone else won; return their order.
            if (command.idempotencyKey() != null && !command.idempotencyKey().isBlank()) {
                Order winner = orders.findByIdempotencyKey(command.userId(), command.idempotencyKey())
                        .orElseThrow(() -> race);
                return new PlaceOrderResult(winner, true);
            }
            throw race;
        }
    }
}
