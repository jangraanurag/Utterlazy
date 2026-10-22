package com.utterlazy.order.domain;

import java.math.BigDecimal;

/** One order line. Ids are validated against the restaurant's live menu upstream. */
public record OrderLine(String menuItemId, String variantId, int quantity, BigDecimal unitPrice) {

    public static final int MIN_QUANTITY = 1;
    public static final int MAX_QUANTITY = 20;

    public OrderLine {
        if (menuItemId == null || menuItemId.isBlank()) {
            throw new IllegalArgumentException("menuItemId is required");
        }
        if (quantity < MIN_QUANTITY || quantity > MAX_QUANTITY) {
            throw new IllegalArgumentException(
                    "quantity must be between " + MIN_QUANTITY + " and " + MAX_QUANTITY);
        }
        if (unitPrice == null || unitPrice.signum() < 0) {
            throw new IllegalArgumentException("unitPrice must be >= 0");
        }
    }

    public BigDecimal total() {
        return unitPrice.multiply(BigDecimal.valueOf(quantity));
    }
}
