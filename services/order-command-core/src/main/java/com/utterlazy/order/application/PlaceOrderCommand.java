package com.utterlazy.order.application;

import com.utterlazy.order.domain.OrderChannel;
import com.utterlazy.order.domain.OrderLine;
import com.utterlazy.order.domain.PricingBreakdown;
import java.util.List;
import java.util.UUID;

public record PlaceOrderCommand(
        UUID userId,
        UUID restaurantId,
        UUID cityId,
        OrderChannel channel,
        String idempotencyKey,
        List<OrderLine> lines,
        PricingBreakdown pricing
) {
    public static PlaceOrderCommand of(UUID userId, UUID restaurantId, UUID cityId,
                                       String channel, String idempotencyKey, List<OrderLine> lines) {
        return of(userId, restaurantId, cityId, channel, idempotencyKey, lines, null);
    }

    public static PlaceOrderCommand of(UUID userId, UUID restaurantId, UUID cityId,
                                       String channel, String idempotencyKey, List<OrderLine> lines,
                                       PricingBreakdown pricing) {
        return new PlaceOrderCommand(userId, restaurantId, cityId,
                OrderChannel.from(channel), idempotencyKey, lines, pricing);
    }
}
