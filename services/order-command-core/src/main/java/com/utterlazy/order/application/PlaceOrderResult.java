package com.utterlazy.order.application;

import com.utterlazy.order.domain.Order;

/** Outcome of place-order: {@code replayed} means an idempotent replay. */
public record PlaceOrderResult(Order order, boolean replayed) {
}
