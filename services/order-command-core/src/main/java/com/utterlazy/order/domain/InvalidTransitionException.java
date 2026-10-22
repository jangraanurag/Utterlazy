package com.utterlazy.order.domain;

public class InvalidTransitionException extends RuntimeException {
    public InvalidTransitionException(OrderStatus from, OrderStatus to) {
        super("Illegal order transition: " + from + " -> " + to);
    }
}
