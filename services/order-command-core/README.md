# order-command-core

Wave 2 groundwork: the **framework-free** domain core for the Java order-command service.

This module is intentionally dependency-free so it can be compiled and tested with plain
`javac` while the Spring adapter, Kafka client, and database are wired in later. It owns:

- the order state machine (same statuses as the Laravel catalogue today),
- the place-order command with channel + idempotency semantics,
- domain events (`order.created`, `order.status_changed`),
- ports (`OrderRepository`, `OutboxWriter`, `UnitOfWork`) that the Spring adapter implements.

No production path calls this module yet. `catalog-service` remains the write owner until
the Wave 2 gate is met (see `docs/deploy-runbook.md` §6).

## Build & self-test (no Maven required)

```bash
cd services/order-command-core
rm -rf out && mkdir -p out
javac --release 17 -d out $(find src/main/java src/test/java -name '*.java')
java -cp out com.utterlazy.order.OrderCoreSelfTest
```

Expected: `SELF-TEST PASS (n checks)`.

## Later (Spring adapter)

`pom.xml` and `Dockerfile` are included for the adapter phase:

- REST `POST /orders` (idempotent, `Idempotency-Key`),
- Kafka producer via a transactional outbox table,
- MySQL repository with optimistic locking (`version`),
- consumer of `order.events.v1` for downstream parity checks.

The Laravel checkout becomes a proxy to this service only after shadow-mode parity
(identical responses + events for the same requests) is proven.
