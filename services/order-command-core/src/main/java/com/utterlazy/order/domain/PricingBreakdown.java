package com.utterlazy.order.domain;

import java.math.BigDecimal;

/**
 * Money snapshot produced by the pricing authority (Laravel today, the Java
 * PricingCalculator after cutover). Lines are immutable; money is attached at
 * placement so shadow comparisons can be exact.
 */
public record PricingBreakdown(
        BigDecimal grandTotal,
        BigDecimal tax,
        BigDecimal vendorDiscount,
        BigDecimal deliveryFee,
        BigDecimal platformFee,
        BigDecimal couponDiscount,
        BigDecimal commission,
        BigDecimal commissionGst,
        BigDecimal netPayout
) {
    public PricingBreakdown {
        require(grandTotal, "grandTotal");
        if (grandTotal.signum() <= 0) {
            throw new IllegalArgumentException("grandTotal must be > 0");
        }
        tax = zeroIfNull(tax);
        vendorDiscount = zeroIfNull(vendorDiscount);
        deliveryFee = zeroIfNull(deliveryFee);
        platformFee = zeroIfNull(platformFee);
        couponDiscount = zeroIfNull(couponDiscount);
        commission = zeroIfNull(commission);
        commissionGst = zeroIfNull(commissionGst);
        netPayout = zeroIfNull(netPayout);
    }

    private static void require(BigDecimal value, String field) {
        if (value == null) {
            throw new IllegalArgumentException(field + " is required");
        }
    }

    private static BigDecimal zeroIfNull(BigDecimal value) {
        return value == null ? BigDecimal.ZERO : value;
    }
}
