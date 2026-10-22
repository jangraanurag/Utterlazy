package com.utterlazy.order.web.dto;

import com.utterlazy.order.application.PricingCalculator;

import java.math.BigDecimal;

public record PricingPreflightResponse(
        BigDecimal subtotal,
        BigDecimal vendorDiscount,
        BigDecimal tax,
        BigDecimal deliveryFee,
        BigDecimal platformFee,
        BigDecimal couponDiscount,
        BigDecimal tip,
        BigDecimal grandTotal,
        BigDecimal netOrderValue,
        BigDecimal commission,
        BigDecimal commissionGst,
        BigDecimal netPayout
) {
    public static PricingPreflightResponse from(BigDecimal subtotal, PricingCalculator.Result r) {
        return new PricingPreflightResponse(
                subtotal, r.vendorDiscount(), r.tax(), r.deliveryFee(), r.platformFee(),
                r.couponDiscount(), r.tip(), r.grandTotal(), r.netOrderValue(),
                r.commission(), r.commissionGst(), r.netPayout());
    }
}
