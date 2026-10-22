package com.utterlazy.order.web.dto;

import jakarta.validation.Valid;
import jakarta.validation.constraints.DecimalMin;
import jakarta.validation.constraints.Max;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.math.BigDecimal;
import java.util.List;
import java.util.UUID;

public record PlaceOrderRequest(
        @NotNull UUID userId,
        @NotNull UUID restaurantId,
        UUID cityId,
        String channel,
        @NotNull @Size(min = 1, max = 50) List<@Valid Line> lines,
        @Valid PricingRequest pricing
) {
    public record PricingRequest(
            @NotNull @DecimalMin(value = "0.01") BigDecimal grandTotal,
            BigDecimal tax,
            BigDecimal vendorDiscount,
            BigDecimal deliveryFee,
            BigDecimal platformFee,
            BigDecimal couponDiscount,
            BigDecimal commission,
            BigDecimal commissionGst,
            BigDecimal netPayout
    ) {
    }

    public record Line(
            @NotBlank String menuItemId,
            String variantId,
            @Min(1) @Max(20) int quantity,
            @NotNull @DecimalMin(value = "0.0") BigDecimal unitPrice
    ) {
    }
}
