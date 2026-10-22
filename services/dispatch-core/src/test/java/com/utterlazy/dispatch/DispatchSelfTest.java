package com.utterlazy.dispatch;

import java.util.List;
import java.util.Map;
import java.util.Set;

public final class DispatchSelfTest {
    private static int pass = 0;
    private static int fail = 0;

    public static void main(String[] args) {
        geo();
        policy();
        batching();

        System.out.println();
        if (fail > 0) {
            System.out.println("DISPATCH SELF-TEST FAIL (" + pass + " passed, " + fail + " failed)");
            System.exit(1);
        }
        System.out.println("DISPATCH SELF-TEST PASS (" + pass + " checks)");
    }

    private static void geo() {
        String a = GeoCell.encode(22.7196, 75.8577, 7);
        String b = GeoCell.encode(22.7196, 75.8577, 7);
        String c = GeoCell.encode(22.7197, 75.8578, 7);

        check("geo: deterministic", a.equals(b));
        check("geo: precision length", a.length() == 7);
        check("geo: nearby shares prefix", a.substring(0, 5).equals(c.substring(0, 5)));
        check("geo: different city differs", !a.equals(GeoCell.encode(28.6139, 77.2090, 7)));
        check("geo: distance zero", GeoCell.haversineKm(22.7, 75.8, 22.7, 75.8) == 0.0);
        double oneDegree = GeoCell.haversineKm(0, 0, 1, 0);
        check("geo: one degree latitude ~111km", oneDegree > 110 && oneDegree < 112);

        boolean invalid = false;
        try {
            GeoCell.encode(91, 0, 7);
        } catch (IllegalArgumentException e) {
            invalid = true;
        }
        check("geo: invalid latitude rejected", invalid);
    }

    private static void policy() {
        DispatchPolicy policy = new DispatchPolicy();
        OrderStop order = new OrderStop("o-1", "r-1", 22.70, 75.80, 22.75, 75.85, 15);

        RiderCandidate near = rider("near", 22.705, 75.805, true, true, 0, 0.9, 2, 100);
        RiderCandidate far = rider("far", 22.74, 75.83, true, true, 0, 0.9, 2, 100);
        RiderCandidate unavailable = rider("offline", 22.701, 75.801, false, true, 0, 0.9, 2, 100);
        RiderCandidate unverified = rider("unverified", 22.701, 75.801, true, false, 0, 0.9, 2, 100);
        RiderCandidate stale = rider("stale", 22.701, 75.801, true, true, 0, 0.9, 45, 100);
        RiderCandidate overloaded = rider("busy", 22.701, 75.801, true, true, 2, 0.9, 2, 100);
        RiderCandidate beyond = rider("beyond", 22.90, 75.80, true, true, 0, 0.9, 2, 100);

        List<DispatchPolicy.Score> scores = policy.rank(order,
                List.of(far, near, unavailable, unverified, stale, overloaded, beyond), Set.of(), 1);

        check("policy: only eligible riders ranked", scores.size() == 2);
        check("policy: nearest wins", scores.get(0).riderId().equals("near"));
        check("policy: excluded ids respected",
                policy.rank(order, List.of(near, far), Set.of("near"), 1).get(0).riderId().equals("far"));

        RiderCandidate newRider = rider("new", 22.706, 75.806, true, true, 0, 0.5, 2, 3);
        RiderCandidate veteran = rider("vet", 22.705, 75.805, true, true, 0, 0.99, 2, 200);
        Map<String, Double> newBreakdown = find(policy.rank(order, List.of(newRider), Set.of(), 1), "new").breakdown();
        check("policy: new rider boost present", newBreakdown.get("exploration") > 0);

        List<DispatchPolicy.Score> exploration = policy.rank(order, List.of(newRider, veteran), Set.of(), 8);
        check("policy: exploration round prefers new rider", exploration.get(0).riderId().equals("new"));

        List<DispatchPolicy.Score> normal = policy.rank(order, List.of(newRider, veteran), Set.of(), 1);
        check("policy: non-exploration prefers stronger veteran", normal.get(0).riderId().equals("vet"));

        RiderCandidate tieA = rider("b-rider", 22.705, 75.805, true, true, 0, 0.9, 2, 100);
        RiderCandidate tieB = rider("a-rider", 22.705, 75.805, true, true, 0, 0.9, 2, 100);
        check("policy: deterministic tie-break",
                policy.rank(order, List.of(tieA, tieB), Set.of(), 1).get(0).riderId().equals("a-rider"));

        boolean invalid = false;
        try {
            rider("bad", 22.7, 75.8, true, true, 0, 1.5, 2, 10);
        } catch (IllegalArgumentException e) {
            invalid = true;
        }
        check("policy: invalid acceptance rejected", invalid);
    }

    private static void batching() {
        BatchPlanner planner = new BatchPlanner();
        OrderStop a = new OrderStop("a", "r", 22.70, 75.80, 22.75, 75.85, 10);
        OrderStop b = new OrderStop("b", "r", 22.701, 75.801, 22.751, 75.851, 10);
        OrderStop far = new OrderStop("c", "r", 22.90, 76.00, 23.00, 76.10, 10);

        List<BatchPlanner.Batch> batches = planner.plan(List.of(a, b, far), 1.0, 1.0, 2);
        check("batch: close orders share a batch", batches.get(0).orders().size() == 2);
        check("batch: far order separate", batches.size() == 2);
        check("batch: max size respected", planner.plan(List.of(a, b), 1.0, 1.0, 1).get(0).orders().size() == 1);
        check("batch: all orders preserved",
                batches.stream().mapToInt(x -> x.orders().size()).sum() == 3);

        boolean invalid = false;
        try {
            planner.plan(List.of(a), 1, 1, 5);
        } catch (IllegalArgumentException e) {
            invalid = true;
        }
        check("batch: invalid max rejected", invalid);
    }

    private static RiderCandidate rider(String id, double lat, double lng, boolean available, boolean verified,
                                        int active, double acceptance, int ping, int trips) {
        return new RiderCandidate(id, lat, lng, available, verified, active, acceptance, ping, trips);
    }

    private static DispatchPolicy.Score find(List<DispatchPolicy.Score> scores, String id) {
        return scores.stream().filter(s -> s.riderId().equals(id)).findFirst().orElseThrow();
    }

    private static void check(String label, boolean ok) {
        if (ok) {
            pass++;
            System.out.println("  PASS  " + label);
        } else {
            fail++;
            System.out.println("  FAIL  " + label);
        }
    }
}
