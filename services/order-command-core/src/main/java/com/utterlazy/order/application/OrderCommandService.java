package com.utterlazy.order.application;

import com.utterlazy.order.domain.DomainEvent;
import com.utterlazy.order.domain.Order;
import com.utterlazy.order.port.OrderRepository;
import com.utterlazy.order.port.OutboxWriter;
import com.utterlazy.order.port.UnitOfWork;

import java.util.List;
import java.util.UUID;
import java.util.function.Consumer;

/**
 * Status-change use cases. Every mutation is saved and its events are written
 * to the outbox inside one unit of work; callers never touch the aggregate
 * without persisting the resulting events.
 */
public final class OrderCommandService {

    private final OrderRepository orders;
    private final OutboxWriter outbox;
    private final UnitOfWork unitOfWork;

    public OrderCommandService(OrderRepository orders, OutboxWriter outbox, UnitOfWork unitOfWork) {
        this.orders = orders;
        this.outbox = outbox;
        this.unitOfWork = unitOfWork;
    }

    public Order accept(UUID orderId) {
        return mutate(orderId, Order::accept);
    }

    public Order startPreparing(UUID orderId) {
        return mutate(orderId, Order::startPreparing);
    }

    public Order markReady(UUID orderId) {
        return mutate(orderId, Order::markReady);
    }

    public Order pickup(UUID orderId) {
        return mutate(orderId, Order::pickup);
    }

    public Order deliver(UUID orderId) {
        return mutate(orderId, Order::deliver);
    }

    public Order cancel(UUID orderId, String reason) {
        return mutate(orderId, order -> order.cancel(reason));
    }

    public Order abandon(UUID orderId, String reason) {
        return mutate(orderId, order -> order.abandon(reason));
    }

    public Order rescue(UUID orderId) {
        return mutate(orderId, Order::rescue);
    }

    private Order mutate(UUID orderId, Consumer<Order> action) {
        return unitOfWork.inTransaction(() -> {
            Order order = orders.findById(orderId)
                    .orElseThrow(() -> new IllegalArgumentException("Order not found: " + orderId));

            action.accept(order);
            orders.save(order);

            List<DomainEvent> events = order.pullEvents();
            outbox.append(events);

            return order;
        });
    }
}
