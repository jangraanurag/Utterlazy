package com.utterlazy.order.domain;

/** Where the order was created. Same order aggregate for every channel. */
public enum OrderChannel {
    APP,
    WEB,
    WHATSAPP;

    public static OrderChannel from(String raw) {
        if (raw == null || raw.isBlank()) {
            return APP;
        }
        try {
            return valueOf(raw.trim().toUpperCase());
        } catch (IllegalArgumentException e) {
            throw new IllegalArgumentException("Unsupported order channel: " + raw);
        }
    }
}
