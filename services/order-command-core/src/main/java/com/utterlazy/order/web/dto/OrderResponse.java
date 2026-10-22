package com.utterlazy.order.web.dto;

import com.utterlazy.order.domain.Order;
import com.utterlazy.order.domain.OrderLine;

import java.math.BigDecimal;
import java.time.Instant;
import java.util.List;
import java.util.UUID;

public record OrderResponse(
        UUID id,
        UUID userId,
        UUID restaurantId,
        UUID cityId,
        String channel,
        String status,
        String cancelReason,
        BigDecimal grandTotal,
        BigDecimal tax,
        BigDecimal vendorDiscount,
        BigDecimal deliveryFee,
        BigDecimal platformFee,
        BigDecimal couponDiscount,
        BigDecimal commission,
        BigDecimal commissionGst,
        BigDecimal netPayout,
        long version,
        Instant createdAt,
        List<Line> lines
) {
    public record Line(String menuItemId, String variantId, int quantity, BigDecimal unitPrice,
                       BigDecimal lineTotal) {
    }

    public static OrderResponse from(Order order) {
        return new OrderResponse(
                order.id(),
                order.userId(),
                order.restaurantId(),
                order.cityId(),
                order.channel().name(),
                order.status().name(),
                order.cancelReason(),
                order.grandTotal(),
                order.pricing() == null ? BigDecimal.ZERO : order.pricing().tax(),
                order.pricing() == null ? BigDecimal.ZERO : order.pricing().vendorDiscount(),
                order.pricing() == null ? BigDecimal.ZERO : order.pricing().deliveryFee(),
                order.pricing() == null ? BigDecimal.ZERO : order.pricing().platformFee(),
                order.pricing() == null ? BigDecimal.ZERO : order.pricing().couponDiscount(),
                order.pricing() == null ? BigDecimal.ZERO : order.pricing().commission(),
                order.pricing() == null ? BigDecimal.ZERO : order.pricing().commissionGst(),
                order.pricing() == null ? BigDecimal.ZERO : order.pricing().netPayout(),
                order.version(),
                order.createdAt(),
                order.lines().stream().map(OrderResponse::line).toList()
        );
    }

    private static Line line(OrderLine line) {
        return new Line(line.menuItemId(), line.variantId(), line.quantity(),
                line.unitPrice(), line.total());
    }
}
