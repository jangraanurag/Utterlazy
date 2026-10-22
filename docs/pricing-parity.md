# 💰 Pricing parity contract (Wave 2 cutover gate)

Pricing must produce **identical persisted money fields** in Laravel and Java before any
checkout traffic moves. This is enforced by a cross-language golden-vector check.

## The formula (canonical)

Inputs: subtotal, vendor discount rate, tax rate, delivery min/max/estimate/free-above,
platform fee, coupon amount, tip, commission rate, GST on commission.

```
vendorDiscount   = subtotal × vendorRate / 100
afterDiscount    = subtotal − vendorDiscount
tax              = afterDiscount × taxRate / 100
deliveryFee      = clamp(estimate, min, max), rounded 2dp
                   = 0 if subtotal ≥ freeAbove
grandTotal       = afterDiscount + tax + deliveryFee + platformFee
couponApplied    = min(coupon, grandTotal, not below 0)
grandTotal       = grandTotal − couponApplied + tip
netOrderValue    = subtotal − vendorDiscount − couponApplied
commission       = max(0, netOrderValue × commissionRate / 100)
commissionGst    = commission × gstOnCommission / 100
netPayout        = (netOrderValue + tax) − commission − commissionGst
```

Rules:

- Intermediates are **not** rounded (matches the current checkout).
- Persisted outputs are rounded to **2 dp, HALF_UP** (what MySQL DECIMAL stores).
- Money is never a float in Java (`BigDecimal`, DECIMAL128 intermediates).
- One discount per order (`couponApplied` is the value chosen by the adapter).

## In scope / out of scope

| In scope (must match) | Out of scope (adapter owns) |
|---|---|
| vendor discount, tax, delivery clamp/free, platform fee | coupon/BOGO/campaign **selection** |
| coupon application, tip | wallet balance checks |
| commission, GST on commission, net payout | dynamic delivery estimate inputs (rider rates) |
| rounding at persistence | payment capture, refunds |

## Shadow wiring

The shadow mirror sends the Laravel price snapshot (`pricing` object) with every order.
Java persists it and echoes it in `GET /orders/{id}`. `order-command:compare` now checks
`grand_total`, `tax`, `vendor_discount`, `delivery_fee`, `platform_fee`, `coupon_discount`,
`commission`, and `net_payout` — so a drifting formula fails the parity gate immediately.

## Production preflight (certification)

Golden vectors prove the formula offline. Preflight proves it on **real orders** without
moving writes:

- Enable `ORDER_COMMAND_CUTOVER_ENABLED=true` and allowlist one city/restaurant
  (`ORDER_COMMAND_CITIES` / `ORDER_COMMAND_RESTAURANTS`); keep `ORDER_COMMAND_CUTOVER_READY=false`.
- Every order created in scope dispatches `CheckPricingParity` after commit.
- The job calls `POST /api/pricing/preflight` on the Java service, compares all persisted
  money fields, and writes `pricing_parity_checks` (matched/mismatch + diffs).
- Checkout is never blocked; failures are recorded and logged.

Review:

```bash
php artisan pricing:parity-report --hours=24
# admin: GET /api/admin/pricing-parity?hours=24
# Command Center: Pricing parity (24h) card
```

Certification: 7 consecutive days, 100% matched, no error rows. Then set
`ORDER_COMMAND_CUTOVER_READY=true` (router wiring remains a separate change).

## Enforcement

```bash
./scripts/pricing_parity.sh
```

1. `php artisan pricing:vectors --write=...` generates canonical vectors from the
   Laravel mirror (`App\Services\Pricing\PricingParityService`).
2. The committed `infra/pricing/golden.tsv` must match (drift fails the gate).
3. `PricingParityCli` recomputes every vector with
   `com.utterlazy.order.application.PricingCalculator` and compares all persisted fields.

CI job `pricing-parity` runs this on every PR touching pricing, the Java core, or the golden file.

## Vectors covered

| id | Why |
|---|---|
| basic | standard order, 5% vendor discount + 5% tax |
| free_delivery | subtotal above free threshold |
| coupon | coupon applied before tip |
| tip | tip excluded from commission base |
| no_gst | 0% tax restaurant |
| delivery_min / delivery_max | clamp both ends |
| combined | coupon + tip + delivery |
| paise_edge | fractional prices |
| free_and_coupon | free delivery plus coupon |

Add a vector whenever the formula changes; never edit expected values by hand without a
matching code change.

## Cutover gate (still required after this)

1. Pricing parity green (this document).
2. Shadow parity: 50k replayed checkouts, zero mismatch in status/total/lines.
3. Adapter maps `PricingCalculator.Input` from live config and the coupon engine's result.
4. City allowlist + `ORDER_COMMAND_CUTOVER_READY=true`, one city at a time.

Until then the router keeps traffic on Laravel (`docs/wave2-order-command.md`).
