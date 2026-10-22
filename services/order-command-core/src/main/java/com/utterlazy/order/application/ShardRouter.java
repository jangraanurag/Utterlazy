package com.utterlazy.order.application;

import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.util.UUID;

/**
 * Deterministic city -> shard routing for the Wave 2 orders DB split.
 * Same city always lands on the same shard; distribution is stable across nodes.
 */
public final class ShardRouter {
    public int shardFor(UUID cityId, int shards) {
        if (shards < 1) {
            throw new IllegalArgumentException("shards must be >= 1");
        }
        if (cityId == null) {
            throw new IllegalArgumentException("cityId is required");
        }

        try {
            byte[] digest = MessageDigest.getInstance("SHA-256")
                    .digest(cityId.toString().getBytes(StandardCharsets.UTF_8));
            int value = ((digest[0] & 0xff) << 24)
                    | ((digest[1] & 0xff) << 16)
                    | ((digest[2] & 0xff) << 8)
                    | (digest[3] & 0xff);

            return Math.floorMod(value, shards);
        } catch (NoSuchAlgorithmException e) {
            throw new IllegalStateException("SHA-256 unavailable", e);
        }
    }
}
