package com.utterlazy.dispatch;

import java.time.Duration;
import java.time.Instant;
import java.util.ArrayList;
import java.util.List;
import java.util.Map;
import java.util.Optional;
import java.util.concurrent.ConcurrentHashMap;

/**
 * In-memory offer timer semantics for rider assignment. Production persists
 * offers in Redis/DB; this is the tested policy for expiry/accept/reject.
 */
public final class OfferTracker {
    public enum Status { PENDING, ACCEPTED, REJECTED, EXPIRED }

    public record Offer(String assignmentId, String orderId, String riderId, Instant createdAt,
                        Instant expiresAt, Status status) {
        public boolean isOpen(Instant now) {
            return status == Status.PENDING && !now.isAfter(expiresAt);
        }
    }

    private final Duration ttl;
    private final Map<String, Offer> offers = new ConcurrentHashMap<>();

    public OfferTracker(Duration ttl) {
        if (ttl == null || ttl.isNegative() || ttl.isZero()) {
            throw new IllegalArgumentException("ttl must be positive");
        }
        this.ttl = ttl;
    }

    public Offer create(String assignmentId, String orderId, String riderId, Instant now) {
        if (assignmentId == null || assignmentId.isBlank() || orderId == null || orderId.isBlank()
                || riderId == null || riderId.isBlank()) {
            throw new IllegalArgumentException("assignmentId, orderId and riderId are required");
        }
        Offer offer = new Offer(assignmentId, orderId, riderId, now, now.plus(ttl), Status.PENDING);
        offers.put(assignmentId, offer);
        return offer;
    }

    public Optional<Offer> get(String assignmentId) {
        return Optional.ofNullable(offers.get(assignmentId));
    }

    public boolean accept(String assignmentId, Instant now) {
        return transition(assignmentId, now, Status.ACCEPTED);
    }

    public boolean reject(String assignmentId, Instant now) {
        return transition(assignmentId, now, Status.REJECTED);
    }

    /** Mark all due offers expired; returns how many changed. Idempotent. */
    public int expireDue(Instant now) {
        int expired = 0;
        for (Map.Entry<String, Offer> entry : offers.entrySet()) {
            Offer offer = entry.getValue();
            if (offer.status() == Status.PENDING && now.isAfter(offer.expiresAt())) {
                offers.put(entry.getKey(), withStatus(offer, Status.EXPIRED));
                expired++;
            }
        }
        return expired;
    }

    /** Rider ids with a live pending offer for the order (to exclude on retry). */
    public List<String> pendingRiders(String orderId, Instant now) {
        List<String> riders = new ArrayList<>();
        for (Offer offer : offers.values()) {
            if (offer.orderId().equals(orderId) && offer.isOpen(now)) {
                riders.add(offer.riderId());
            }
        }
        return riders;
    }

    private boolean transition(String assignmentId, Instant now, Status target) {
        Offer offer = offers.get(assignmentId);
        if (offer == null || !offer.isOpen(now)) {
            return false;
        }
        offers.put(assignmentId, withStatus(offer, target));
        return true;
    }

    private Offer withStatus(Offer offer, Status status) {
        return new Offer(offer.assignmentId(), offer.orderId(), offer.riderId(), offer.createdAt(),
                offer.expiresAt(), status);
    }
}
