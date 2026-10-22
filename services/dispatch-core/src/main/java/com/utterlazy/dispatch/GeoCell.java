package com.utterlazy.dispatch;

/** Geohash cell keys (H3-equivalent groundwork without a native dependency). */
public final class GeoCell {
    private static final String BASE32 = "0123456789bcdefghjkmnpqrstuvwxyz";

    private GeoCell() {
    }

    public static String encode(double lat, double lng, int precision) {
        if (precision < 1 || precision > 12) {
            throw new IllegalArgumentException("precision must be 1..12");
        }
        if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
            throw new IllegalArgumentException("coordinates out of range");
        }

        double latMin = -90, latMax = 90, lngMin = -180, lngMax = 180;
        StringBuilder hash = new StringBuilder(precision);
        boolean even = true;
        int bit = 0;
        int ch = 0;

        while (hash.length() < precision) {
            if (even) {
                double mid = (lngMin + lngMax) / 2;
                if (lng >= mid) {
                    ch = (ch << 1) | 1;
                    lngMin = mid;
                } else {
                    ch <<= 1;
                    lngMax = mid;
                }
            } else {
                double mid = (latMin + latMax) / 2;
                if (lat >= mid) {
                    ch = (ch << 1) | 1;
                    latMin = mid;
                } else {
                    ch <<= 1;
                    latMax = mid;
                }
            }

            even = !even;
            if (++bit == 5) {
                hash.append(BASE32.charAt(ch));
                bit = 0;
                ch = 0;
            }
        }

        return hash.toString();
    }

    public static double haversineKm(double lat1, double lng1, double lat2, double lng2) {
        double earth = 6371.0;
        double dLat = Math.toRadians(lat2 - lat1);
        double dLng = Math.toRadians(lng2 - lng1);
        double a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
                + Math.cos(Math.toRadians(lat1)) * Math.cos(Math.toRadians(lat2))
                * Math.sin(dLng / 2) * Math.sin(dLng / 2);

        return earth * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }
}
