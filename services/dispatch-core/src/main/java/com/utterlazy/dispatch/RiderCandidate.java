package com.utterlazy.dispatch;

public record RiderCandidate(
        String riderId,
        double lat,
        double lng,
        boolean available,
        boolean verified,
        int activeOrders,
        double acceptanceRate,
        int minutesSincePing,
        int totalTrips
) {
    public RiderCandidate {
        if (riderId == null || riderId.isBlank()) {
            throw new IllegalArgumentException("riderId is required");
        }
        if (acceptanceRate < 0 || acceptanceRate > 1) {
            throw new IllegalArgumentException("acceptanceRate must be 0..1");
        }
        if (activeOrders < 0 || minutesSincePing < 0 || totalTrips < 0) {
            throw new IllegalArgumentException("counters must be >= 0");
        }
    }
}
