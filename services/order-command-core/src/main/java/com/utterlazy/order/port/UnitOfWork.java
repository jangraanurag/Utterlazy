package com.utterlazy.order.port;

/**
 * Explicit unit-of-work boundary so order state and its events commit together.
 * The Spring adapter maps this to a single database transaction.
 */
public interface UnitOfWork {
    <T> T inTransaction(Work<T> work);

    @FunctionalInterface
    interface Work<T> {
        T run();
    }
}
