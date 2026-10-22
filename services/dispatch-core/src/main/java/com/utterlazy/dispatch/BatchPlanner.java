package com.utterlazy.dispatch;

import java.util.ArrayList;
import java.util.List;

/**
 * Greedy batching: orders can share a rider when pickups and drops are close.
 * Keeps batches small and deterministic (input order preserved).
 */
public final class BatchPlanner {
    public record Batch(List<OrderStop> orders) {
    }

    public List<Batch> plan(List<OrderStop> orders, double pickupRadiusKm, double dropRadiusKm, int maxPerBatch) {
        if (maxPerBatch < 1 || maxPerBatch > 3) {
            throw new IllegalArgumentException("maxPerBatch must be 1..3");
        }

        List<OrderStop> remaining = new ArrayList<>(orders);
        List<Batch> batches = new ArrayList<>();

        while (!remaining.isEmpty()) {
            OrderStop seed = remaining.remove(0);
            List<OrderStop> group = new ArrayList<>();
            group.add(seed);

            for (int i = 0; i < remaining.size() && group.size() < maxPerBatch; ) {
                OrderStop candidate = remaining.get(i);
                boolean samePickup = GeoCell.haversineKm(
                        seed.pickupLat(), seed.pickupLng(), candidate.pickupLat(), candidate.pickupLng()) <= pickupRadiusKm;
                boolean sameDrop = GeoCell.haversineKm(
                        seed.dropLat(), seed.dropLng(), candidate.dropLat(), candidate.dropLng()) <= dropRadiusKm;

                if (samePickup && sameDrop) {
                    group.add(candidate);
                    remaining.remove(i);
                } else {
                    i++;
                }
            }

            batches.add(new Batch(List.copyOf(group)));
        }

        return batches;
    }
}
