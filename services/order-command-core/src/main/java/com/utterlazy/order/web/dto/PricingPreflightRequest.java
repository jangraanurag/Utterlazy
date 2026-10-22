package com.utterlazy.order.web.dto;

import com.utterlazy.order.web.dto.PlaceOrderRequest.Line;
import jakarta.validation.Valid;
import jakarta.validation.constraints.DecimalMin;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.math.BigDecimal;
import java.util.List;

/**
 * Price-only check used during cutover certification. No order is written.
 * Every rate/fee is explicit so the caller (Laravel) stays the config authority.
 */
public record PricingPreflightRequest(
        @NotNull @Size(min = 1, max = 50) List<@Valid Line> lines,
        @NotNull @DecimalMin("0.0") BigDecimal vendorDiscountRate,
        @NotNull @DecimalMin("0.0") BigDecimal taxRate,
        @NotNull @DecimalMin("0.0") BigDecimal deliveryMin,
        @NotNull @DecimalMin("0.0") BigDecimal deliveryMax,
        @NotNull @DecimalMin("0.0") BigDecimal deliveryEstimate,
        @NotNull @DecimalMin("0.0") BigDecimal deliveryFreeAbove,
        @NotNull @DecimalMin("0.0") BigDecimal platformFee,
        @NotNull @DecimalMin("0.0") BigDecimal coupon,
        @NotNull @DecimalMin("0.0") BigDecimal tip,
        @NotNull @DecimalMin("0.0") BigDecimal commissionRate,
        @NotNull @DecimalMin("0.0") BigDecimal commissionGstRate
) {
}
