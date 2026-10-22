package com.utterlazy.dispatch;

import java.util.ArrayList;
import java.util.Comparator;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import java.util.Set;

/**
 * Deterministic rider scoring and assignment policy.
 *
 * - excludes unavailable, unverified, stale-ping and overloaded riders
 * - caps pickup distance
 * - scores distance, workload, acceptance, ping freshness
 * - every Nth assignment prefers riders with <20 trips (anti-starvation)
 */
public final class DispatchPolicy {
    public static final double MAX_PICKUP_KM = 6.0;
    public static final int STALE_PING_MINUTES = 30;
    public static final int MAX_ACTIVE_ORDERS = 1;
    public static final int NEW_RIDER_TRIP_FLOOR = 20;
    public static final double NEW_RIDER_BOOST = 0.05;
    public static final int EXPLORATION_EVERY = 8;

    private static final Map<String, Double> WEIGHTS = new LinkedHashMap<>();

    static {
        WEIGHTS.put("distance", 0.45);
        WEIGHTS.put("workload", 0.20);
        WEIGHTS.put("acceptance", 0.15);
        WEIGHTS.put("freshness", 0.10);
        WEIGHTS.put("exploration", 0.10);
    }

    public record Score(String riderId, double score, Map<String, Double> breakdown) {
    }

    public List<Score> rank(OrderStop order, List<RiderCandidate> riders, Set<String> excluded, int assignmentSequence) {
        List<RiderCandidate> eligible = new ArrayList<>();

        for (RiderCandidate rider : riders) {
            if (excluded.contains(rider.riderId())) continue;
            if (!rider.available() || !rider.verified()) continue;
            if (rider.activeOrders() > MAX_ACTIVE_ORDERS) continue;
            if (rider.minutesSincePing() > STALE_PING_MINUTES) continue;

            double pickupKm = GeoCell.haversineKm(order.pickupLat(), order.pickupLng(), rider.lat(), rider.lng());
            if (pickupKm > MAX_PICKUP_KM) continue;

            eligible.add(rider);
        }

        boolean explorationRound = assignmentSequence > 0 && assignmentSequence % EXPLORATION_EVERY == 0;
        if (explorationRound) {
            List<RiderCandidate> newRiders = eligible.stream()
                    .filter(r -> r.totalTrips() < NEW_RIDER_TRIP_FLOOR)
                    .toList();

            if (!newRiders.isEmpty()) {
                eligible = new ArrayList<>(newRiders);
            }
        }

        List<Score> scores = new ArrayList<>();
        for (RiderCandidate rider : eligible) {
            double pickupKm = GeoCell.haversineKm(order.pickupLat(), order.pickupLng(), rider.lat(), rider.lng());

            double distanceScore = clamp(1.0 - (pickupKm / MAX_PICKUP_KM));
            double workloadScore = clamp(1.0 - (rider.activeOrders() / (double) (MAX_ACTIVE_ORDERS + 1)));
            double acceptanceScore = clamp(rider.acceptanceRate());
            double freshnessScore = clamp(1.0 - (rider.minutesSincePing() / (double) STALE_PING_MINUTES));
            double exploration = rider.totalTrips() < NEW_RIDER_TRIP_FLOOR ? NEW_RIDER_BOOST : 0.0;

            Map<String, Double> breakdown = new LinkedHashMap<>();
            breakdown.put("distance", round(distanceScore * WEIGHTS.get("distance")));
            breakdown.put("workload", round(workloadScore * WEIGHTS.get("workload")));
            breakdown.put("acceptance", round(acceptanceScore * WEIGHTS.get("acceptance")));
            breakdown.put("freshness", round(freshnessScore * WEIGHTS.get("freshness")));
            breakdown.put("exploration", round(exploration));

            double total = round(breakdown.values().stream().mapToDouble(Double::doubleValue).sum());
            scores.add(new Score(rider.riderId(), total, breakdown));
        }

        scores.sort(Comparator.comparingDouble(Score::score).reversed()
                .thenComparing(Score::riderId));

        return scores;
    }

    private static double clamp(double value) {
        return Math.max(0.0, Math.min(1.0, value));
    }

    private static double round(double value) {
        return Math.round(value * 1_000_000d) / 1_000_000d;
    }
}
