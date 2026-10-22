package com.utterlazy.order;

import com.utterlazy.order.application.PlaceOrderCommand;
import com.utterlazy.order.application.OrderCommandService;
import com.utterlazy.order.application.PlaceOrderHandler;
import com.utterlazy.order.domain.DomainEvent;
import com.utterlazy.order.domain.InvalidTransitionException;
import com.utterlazy.order.domain.Order;
import com.utterlazy.order.domain.OrderChannel;
import com.utterlazy.order.domain.OrderLine;
import com.utterlazy.order.domain.OrderStatus;
import com.utterlazy.order.domain.PricingBreakdown;
import com.utterlazy.order.port.OrderRepository;
import com.utterlazy.order.port.OutboxWriter;
import com.utterlazy.order.port.UnitOfWork;

import java.math.BigDecimal;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.Optional;
import java.util.UUID;

/**
 * Dependency-free self-test. Run with:
 *   java -cp out com.utterlazy.order.OrderCoreSelfTest
 */
public final class OrderCoreSelfTest {

    private static int pass = 0;
    private static int fail = 0;

    public static void main(String[] args) {
        testPlaceOrder();
        testIdempotency();
        testValidation();
        testTransitions();
        testCancelRules();
        testLineLimits();
        testPricingBreakdown();
        testSharding();

        System.out.println();
        if (fail > 0) {
            System.out.println("SELF-TEST FAIL (" + pass + " passed, " + fail + " failed)");
            System.exit(1);
        }
        System.out.println("SELF-TEST PASS (" + pass + " checks)");
    }

    private static void testPlaceOrder() {
        Fixture f = new Fixture();
        Order order = f.handler.handle(f.command("app", null, line("item-1", 2, "100.00"))).order();

        check("place: status pending", order.status() == OrderStatus.PENDING);
        check("place: total computed", order.grandTotal().compareTo(new BigDecimal("200.00")) == 0);
        check("place: event emitted", f.outbox.events.size() == 1 && f.outbox.events.get(0).eventType().equals("order.created"));
    }

    private static void testIdempotency() {
        Fixture f = new Fixture();
        PlaceOrderCommand cmd = f.command("whatsapp", "wa-key-12345678", line("item-1", 1, "50.00"));

        var firstResult = f.handler.handle(cmd);
        var secondResult = f.handler.handle(cmd);
        Order first = firstResult.order();
        Order second = secondResult.order();

        check("idempotency: same order id", first.id().equals(second.id()));
        check("idempotency: first is not replayed", !firstResult.replayed());
        check("idempotency: second is replayed", secondResult.replayed());
        check("idempotency: only one stored order", f.repo.count() == 1);
        check("idempotency: only one created event", f.outbox.events.size() == 1);
    }

    private static void testValidation() {
        Fixture f = new Fixture();

        boolean qty = expect(() -> f.handler.handle(f.command("app", null, line("item-1", 21, "10.00"))));
        check("validation: qty > 20 rejected", qty);

        boolean zero = expect(() -> f.handler.handle(f.command("app", null, line("item-1", 0, "10.00"))));
        check("validation: qty < 1 rejected", zero);

        boolean noKey = expect(() -> f.handler.handle(f.command("whatsapp", null, line("item-1", 1, "10.00"))));
        check("validation: whatsapp without key rejected", noKey);

        boolean badChannel = expect(() -> PlaceOrderCommand.of(UUID.randomUUID(), UUID.randomUUID(),
                UUID.randomUUID(), "carrier-pigeon", null, List.of(line("i", 1, "1.00"))));
        check("validation: unknown channel rejected", badChannel);
    }

    private static void testTransitions() {
        Fixture f = new Fixture();
        Order order = f.handler.handle(f.command("app", null, line("item-1", 1, "100.00"))).order();

        f.service.accept(order.id());
        check("transition: pending -> confirmed", order.status() == OrderStatus.CONFIRMED);

        f.service.startPreparing(order.id());
        f.service.markReady(order.id());
        f.service.pickup(order.id());
        f.service.deliver(order.id());

        check("transition: happy path delivered", order.status() == OrderStatus.DELIVERED);
        check("transition: delivered is terminal", order.isTerminal());
        check("transition: version advanced", order.version() == 5);
        check("transition: status events emitted", f.outbox.events.size() == 6);

        DomainEvent last = f.outbox.events.get(f.outbox.events.size() - 1);
        Order.OrderSnapshot snap = (Order.OrderSnapshot) last.data();
        check("transition: event carries fromStatus", snap.fromStatus() == OrderStatus.OUT_FOR_DELIVERY);

        boolean illegal = expect(() -> order.accept());
        check("transition: delivered cannot accept", illegal);
    }

    private static void testCancelRules() {
        Fixture f = new Fixture();
        Order order = f.handler.handle(f.command("app", null, line("item-1", 1, "100.00"))).order();

        f.service.accept(order.id());
        f.service.cancel(order.id(), "restaurant_reject");
        check("cancel: confirmed -> cancelled", order.status() == OrderStatus.CANCELLED);
        check("cancel: reason stored", "restaurant_reject".equals(order.cancelReason()));

        boolean noReason = expect(() -> {
            Fixture g = new Fixture();
            Order fresh = g.handler.handle(g.command("app", null, line("item-1", 1, "100.00"))).order();
            g.service.cancel(fresh.id(), "  ");
        });
        check("cancel: reason required", noReason);

        Fixture h = new Fixture();
        Order delivered = h.handler.handle(h.command("app", null, line("item-1", 1, "100.00"))).order();
        h.service.accept(delivered.id());
        h.service.startPreparing(delivered.id());
        h.service.markReady(delivered.id());
        h.service.pickup(delivered.id());
        h.service.deliver(delivered.id());

        boolean terminal = expect(() -> h.service.cancel(delivered.id(), "customer"));
        check("cancel: delivered cannot cancel", terminal);
    }

    private static void testLineLimits() {
        Fixture f = new Fixture();
        List<OrderLine> tooMany = new ArrayList<>();
        for (int i = 0; i < 51; i++) {
            tooMany.add(line("item-" + i, 1, "10.00"));
        }

        boolean rejected = expect(() -> Order.place(UUID.randomUUID(), UUID.randomUUID(), UUID.randomUUID(),
                OrderChannel.APP, null, tooMany));
        check("lines: 51 rejected", rejected);

        Order fifty = Order.place(UUID.randomUUID(), UUID.randomUUID(), UUID.randomUUID(),
                OrderChannel.WEB, null, tooMany.subList(0, 50));
        check("lines: 50 accepted", fifty.lines().size() == 50);
    }

    private static void testPricingBreakdown() {
        PricingBreakdown pricing = new PricingBreakdown(
                new BigDecimal("230.50"), new BigDecimal("9.45"), new BigDecimal("9.95"),
                new BigDecimal("27.00"), new BigDecimal("5.00"), new BigDecimal("0.00"),
                new BigDecimal("28.36"), new BigDecimal("5.10"), new BigDecimal("165.04"));

        Order order = Order.place(UUID.randomUUID(), UUID.randomUUID(), UUID.randomUUID(),
                OrderChannel.APP, null, List.of(line("item-1", 1, "199.00")), pricing);

        check("pricing: grand total from breakdown", order.grandTotal().compareTo(new BigDecimal("230.50")) == 0);
        check("pricing: tax carried", order.pricing().tax().compareTo(new BigDecimal("9.45")) == 0);

        DomainEvent created = order.pullEvents().get(0);
        Order.OrderSnapshot snapshot = (Order.OrderSnapshot) created.data();
        check("pricing: snapshot carries breakdown", snapshot.pricing() != null
                && snapshot.pricing().netPayout().compareTo(new BigDecimal("165.04")) == 0);
    }

    private static void testSharding() {
        var router = new com.utterlazy.order.application.ShardRouter();
        java.util.UUID city = java.util.UUID.randomUUID();
        int shards = 16;

        int first = router.shardFor(city, shards);
        check("shard: deterministic", first == router.shardFor(city, shards));
        check("shard: in range", first >= 0 && first < shards);

        java.util.Set<Integer> seen = new java.util.HashSet<>();
        for (int i = 0; i < 200; i++) {
            seen.add(router.shardFor(java.util.UUID.randomUUID(), shards));
        }
        check("shard: spreads across shards", seen.size() > 8);

        boolean invalid = false;
        try {
            router.shardFor(city, 0);
        } catch (IllegalArgumentException e) {
            invalid = true;
        }
        check("shard: zero shards rejected", invalid);
    }

    // ─── fixture ───

    private static OrderLine line(String id, int qty, String price) {
        return new OrderLine(id, null, qty, new BigDecimal(price));
    }

    private static boolean expect(Runnable action) {
        try {
            action.run();
            return false;
        } catch (IllegalArgumentException | InvalidTransitionException e) {
            return true;
        }
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

    private static final class Fixture {
        final InMemoryRepo repo = new InMemoryRepo();
        final RecordingOutbox outbox = new RecordingOutbox();
        final PlaceOrderHandler handler = new PlaceOrderHandler(repo, outbox, new ImmediateUow());
        final OrderCommandService service = new OrderCommandService(repo, outbox, new ImmediateUow());

        PlaceOrderCommand command(String channel, String key, OrderLine line) {
            return PlaceOrderCommand.of(UUID.randomUUID(), UUID.randomUUID(), UUID.randomUUID(),
                    channel, key, List.of(line));
        }
    }

    private static final class InMemoryRepo implements OrderRepository {
        private final Map<UUID, Order> byId = new HashMap<>();
        private final Map<String, Order> byKey = new HashMap<>();

        @Override
        public Optional<Order> findById(UUID id) {
            return Optional.ofNullable(byId.get(id));
        }

        @Override
        public Optional<Order> findByIdempotencyKey(UUID userId, String key) {
            return Optional.ofNullable(byKey.get(userId + "|" + key));
        }

        @Override
        public void save(Order order) {
            byId.put(order.id(), order);
            if (order.idempotencyKey() != null) {
                byKey.put(order.userId() + "|" + order.idempotencyKey(), order);
            }
        }

        int count() {
            return byId.size();
        }
    }

    private static final class RecordingOutbox implements OutboxWriter {
        final List<DomainEvent> events = new ArrayList<>();

        @Override
        public void append(List<DomainEvent> batch) {
            events.addAll(batch);
        }
    }

    private static final class ImmediateUow implements UnitOfWork {
        @Override
        public <T> T inTransaction(Work<T> work) {
            return work.run();
        }
    }
}
