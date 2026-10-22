package com.utterlazy.dispatch;

public record OrderStop(
        String orderId,
        String restaurantId,
        double pickupLat,
        double pickupLng,
        double dropLat,
        double dropLng,
        int prepMinutes
) {
    public OrderStop {
        if (orderId == null || orderId.isBlank()) {
            throw new IllegalArgumentException("orderId is required");
        }
    }
}
