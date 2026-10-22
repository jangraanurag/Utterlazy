# OpenCode Agent Prompt — Rider App (Khaalo/Welo)

Paste this whole prompt into your OpenCode agent as the task brief.

---

## Role & Context

You are a senior full-stack engineer working on **Khaalo (Welo)**:
- **Customer App** (Flutter)
- **Restaurant App** (Flutter)
- **Rider App** (Flutter) — this prompt
- **Admin Panel** (Vue 3 web)
- **Backend**: Laravel 13 microservices

Your task: build the Rider App's five areas — Dashboard, History, the main live-order
screen, Payout, and Profile/KYC — plus the standard supporting screens real delivery
apps ship with (researched below, since the brief explicitly asked to study Zomato/
Swiggy for these).

Build order: **Distance-based pay calculation engine (§1, get this exactly right first
— it's the foundation everything else reports on) → live order-handling screen (§4) →
Dashboard aggregation (§2) → History (§3) → daily Payout (§5) → Profile/KYC (§6) →
supporting screens (§7).**

---

## 1. Rider Pay Formula — build this precisely, boundaries matter

Stated rule: **₹12 per completed km**; for the leftover distance after full kilometers,
**₹6 if the leftover is above 200m and less than 900m**; **above 900m, round up and
count as a full km** (paid at ₹12, not ₹6).

### 1.1 Worked examples
| Total distance | Full km | Leftover | Pay for leftover | Total pay |
|---|---|---|---|---|
| 3.15 km | 3 | 150m (< 200m) | ₹0 | ₹36 |
| 3.50 km | 3 | 500m (200–900m) | ₹6 | ₹42 |
| 3.95 km | 3 | 950m (> 900m) → rounds to 4th km | ₹0 (paid as full km instead) | ₹48 |
| 3.00 km | 3 | 0m | ₹0 | ₹36 |

### 1.2 Boundary gap in the brief — confirm before building
"Above 200m and less than 900m" (for ₹6) and "above 900m" (for round-to-full-km) leaves
**exactly 200m and exactly 900m undefined** by the literal wording — at 200m sharp, is
it in the ₹0 bucket or the ₹6 bucket? At 900m sharp, is it in the ₹6 bucket or the
round-up bucket? **Recommended default** (confirm with Anurag, then hardcode this
explicitly rather than leaving it to whatever a naive `<`/`>` comparison happens to do):
`leftover < 200m → ₹0`, `200m ≤ leftover ≤ 900m → ₹6`, `leftover > 900m → rounds up to
a full paid km`. Write this as an explicit unit test with exact 200m and exact 900m
cases, not just the mid-range examples above.

### 1.3 Two more decisions this formula needs, not covered in the brief
- **Distance source**: use actual routed distance (via a mapping/routing API — road
  distance, not straight-line/haversine) for pay calculation. Haversine will
  systematically underpay riders on winding or blocked routes — this is a fairness and
  retention issue, not just a technical detail.
- **Batched orders**: if the rider is carrying more than one order at once (a real,
  standard pattern at scale — flagged for you to build eventually even if not in v1),
  how is the pay-relevant distance apportioned between the orders sharing a route leg?
  Confirm an apportionment rule (e.g. split by straight-line distance ratio to each
  drop point, or pay full per-order distance for each) before batching is built —
  retrofitting this after riders are used to a certain number is a bad place to make
  changes.

---

## 2. Tab 1 — Dashboard

- **Completed journeys** and **Earnings** — headline numbers for the selected period
- **Top 5 restaurants** — by count of completed orders for this rider, for the
  selected period
- **3-hour time-slot analysis** — bucket the rider's completed orders into 3-hour
  slots (e.g. 12–3pm, 3–6pm, 6–9pm...) across the period and surface which slot had
  the most orders — show as a bar chart across all slots, not just the single winning
  slot, so the rider can see their own pattern
- **View selectors**: Week / Custom date range / Month
- **Comparison**: current period vs the immediately preceding equivalent period (this
  week vs last week, this month vs last month, or the custom range vs an equal-length
  prior range) — show as a delta (%, ↑/↓) next to each headline metric, not just two
  numbers side by side

Compute this from a pre-aggregated `DailyRiderMetric` fact table (mirrors the pattern
recommended in the earlier Reporting module prompt) rather than scanning raw order
history live — the weekly/monthly/custom + comparison queries get expensive otherwise.

---

## 3. Tab 2 — History

- List of all orders for the rider with status: `Completed` / `Cancelled` / `Ongoing`
- **Status history** per order — every status transition with its own timestamp, shown
  in **IST**, regardless of server timezone (e.g. Assigned → Reached Restaurant →
  Picked Up → Reached Customer → Delivered, each timestamped)
- **Earning per order** — the exact pay computed via §1's formula for that order
- **Paid / Unpaid** status per order — reflects whether this order's earning has been
  included in a `Paid` daily payout cycle yet (§5)

---

## 4. Tab 3 — Main Order-Handling Screen (the live/active screen)

This is the screen the rider spends most of their time on — order assignment through
completion, in one place, matching the real-time flow used by Zomato/Swiggy:

1. **New order assignment alert** — push notification + in-app alert with a short
   accept/reject countdown timer (standard pattern to keep dispatch moving; pick a
   timeout value and what happens on timeout — auto-reassign to another rider,
   presumably)
2. **Navigation to restaurant** — map view, live route, ETA
3. **Reached restaurant / Picked up** — rider marks pickup (this status change is what
   feeds the restaurant's order tracking too, so it must be a real status event other
   modules can subscribe to, not a rider-app-only flag)
4. **Navigation to customer** — map view, live route, ETA, with the rider's live
   location streamed to the Customer App (matches the real-time location-update pattern
   used industry-wide)
5. **Delivery confirmation** — recommend **OTP-based confirmation** (customer shares a
   code shown in their app; rider enters it to mark delivered) — this is standard
   practice and prevents delivered-but-not-confirmed disputes, which matters directly
   for the pay/order-status data this whole module depends on
6. **Multiple/batched orders** (flag for later if not v1): the screen needs a design
   that can show 2+ active orders with their own pickup/drop sequence, not just a
   single-order-at-a-time layout, if/when batching is turned on (§1.3)

Every status transition here should write to the same `OrderStatusEvent` structure used
elsewhere in the platform (the Payout and Ops modules already reference an order status
timeline) — don't build a rider-app-only status log that the rest of the system can't
see.

---

## 5. Tab 4 — Payout (daily, not weekly)

This mirrors the Finance Payout module built earlier for restaurants, but at **daily**
cadence and scoped to a rider instead of an outlet:

1. **Generation**: every morning at **8:00 AM**, a batch job computes the previous
   day's earnings for every rider (sum of §1's per-order pay across all `Completed`
   orders from the prior calendar day), creating a `Generated — Pending Payment` cycle.
2. **Admin/Finance review**: Admin/company reviews the generated amount, confirms it,
   performs the actual transfer, and enters the **UTR** and other payment details —
   identical maker/checker shape to the restaurant Payout module (recommend reusing
   that same maker-checker control here, given this is also real money movement).
3. Only after UTR entry does the cycle flip to **Paid**, and only then do that day's
   orders in History (§3) flip from Unpaid to Paid.
4. **Recommend** (not explicit in the brief, but needed for a real system): a running
   ledger per rider, same as the restaurant Payout module, to handle any deductions
   (e.g. damage/loss recovery, advance recovery if Khaalo ever fronts uniform/bag costs
   — see §6) — confirm whether riders have any deduction types at all before building
   this, since the brief only described positive per-km earnings.

---

## 6. Profile & KYC (researched from Zomato/Swiggy delivery-partner onboarding)

Real onboarding for delivery partners on Zomato/Swiggy collects and verifies:
- **Aadhaar card**
- **PAN card**
- **Driving License**
- **Bank account details** (for the daily payout, §5)
- **Vehicle Registration Certificate (RC)**
- **Vehicle Insurance**
- Profile photo and vehicle photo
- A unique Rider/Partner ID (shown in Profile — mirrors Zomato's "Fleet Executive ID"
  concept), used in support conversations and as a reference across other modules

Recommend a **verification status** field per rider (Pending / Verified / Rejected —
resubmit) so Admin/Ops can gate a rider going live until documents are checked — this
is standard practice and a real trust/safety requirement, not just paperwork.

**Also recommend** (seen across both major platforms, flag for scope decision):
document expiry tracking (DL, insurance, RC all expire — build reminders before they
lapse, similar to the FSSAI-expiry reminder pattern already recommended for restaurants
in the earlier Ops master-spec) — an expired document quietly going unnoticed is both a
compliance and a safety risk.

---

## 7. Other Supporting Screens (researched — "get idea from Zomato/Swiggy")

Both major platforms ship these around the core order/payout/profile flow; recommend
including at least the first few in scope, and flagging the rest for a later phase:

- **Earnings breakdown** — base per-km pay, incentive/bonus pay, surge/peak-hour pay
  (if Khaalo builds a surge model), shown separately, not just a single lump earnings
  number — ties into the Dashboard (§2)
- **Incentive/bonus schemes** — e.g. bonus for completing X orders in a day/week, peak-
  hour bonus — a real driver of rider retention on both platforms; flag as its own
  future module, not something to improvise inside the payout formula
- **Safety** — an SOS/emergency button, and (both platforms mention this) accidental/
  medical insurance coverage for riders while on active duty — confirm with Anurag
  whether Khaalo will offer a comparable insurance benefit, since it's a meaningful
  part of how riders evaluate a platform
- **Support/Help** — in-app chat and/or call support, reachable from Profile; ties
  directly into the Support/CRM system from the earlier Ops master-spec (rider issues
  are already a planned ticket category there — reuse that system, don't build a
  parallel one)
- **Masked calling** — when a rider needs to call the customer or restaurant, route
  through a number-masking service rather than exposing real phone numbers, for privacy
  and safety
- **Ratings** — customers rate the delivery experience; show the rider their own
  rating trend, similar to how restaurants see theirs
- **Referral program** — refer-a-rider rewards, common growth lever on both platforms
- **Training/onboarding module** — short in-app training before a new rider goes live
- **Online/Offline (shift) toggle** — rider explicitly marks themselves available/
  unavailable for new order assignment, rather than the app inferring it
- **Notifications** and **language selection** — standard, low-effort inclusions

---

## 8. Data Model

- `RiderOrder` — order_id, rider_id, status, status history (or link to a shared
  `OrderStatusEvent` table, §4), distance_km, pay_amount (computed via §1), paid_status
- `RiderPayoutCycle` — rider_id, cycle_date (single day, not a week), status
  (Generated/Paid), total_amount, utr_number, payment_date, entered_by, approved_by
  (if maker-checker adopted)
- `RiderProfile` — personal details, vehicle details, KYC document references,
  verification_status, document expiry dates
- `DailyRiderMetric` — rider_id, date, orders_completed, earnings, restaurant_id
  breakdown, time_slot breakdown — feeds Dashboard (§2) the same way the earlier
  Reporting module's fact table feeds restaurant dashboards
- Reuse `SupportTicket` (from the Ops master-spec) for rider support/help, tagged with
  a rider-specific category rather than a new ticketing system

---

## 9. Cross-Platform / Staffing Notes

- **Admin Panel** needs a Rider Ops view: manage rider KYC verification (§6), review/
  approve daily payouts (§5), monitor live rider status/location for ops purposes,
  handle rider-raised support tickets and escalations.
- Recommend a **Rider Ops / Fleet team** role (parallel to the Finance/Payout team
  already defined for restaurants) responsible for: KYC verification queue, daily
  payout maker/checker, document-expiry follow-ups, and first-line rider support before
  escalating to the platform-wide Grievance Officer (from the Ops master-spec) if a
  rider's complaint becomes a formal legal grievance.

---

## 10. Validation & Edge Cases

- Exact-200m and exact-900m boundary cases explicitly tested (§1.2)
- Zero-distance or same-location edge case (e.g. a cancelled-before-pickup order) —
  should not produce a nonsensical pay amount
- A rider with zero completed orders in a day still gets a (zero-amount) payout cycle
  record for continuity, matching the pattern used in the restaurant Payout module
- IST timestamps in History (§3) must be correct regardless of server timezone —
  don't let this silently default to UTC
- KYC-unverified riders should not be able to go online/receive order assignments (§6)

---

## 11. Deliverables Checklist for the Agent

- [x] Distance-based pay calculation engine with explicit boundary handling and unit
      tests (§1)
- [x] Main order-handling screen: assignment alert, navigation, pickup/delivery status
      updates, OTP delivery confirmation (§4) — Pre-existing
- [x] `DailyRiderMetric` fact table + Dashboard with comparison logic (§2)
- [x] History tab with full IST-timestamped status history and paid/unpaid state (§3)
- [x] Daily 8 AM payout generation + Admin maker-checker UTR flow (§5)
- [x] Profile/KYC with document upload, verification status, expiry tracking (§6)
- [x] Supporting screens per §7, scoped per Anurag's phasing decision
- [x] Admin Panel Rider Ops view (§9) — KYC Review + Cycle Payouts pages
- [x] Flag all open questions below to Anurag before finalizing that part of the logic

---

## Open Questions to Resolve Before/During Build (do not silently assume)

1. Exact boundary behavior at 200m and 900m leftover distance — confirm the recommended
   default in §1.2 or specify an alternative.
2. Routed (road) distance vs straight-line — confirm the mapping/routing provider to
   integrate for pay-relevant distance calculation. (§1.3)
3. Batching apportionment rule — needed before batching ships, not necessarily for v1.
   (§1.3)
4. Do riders have any deduction types (damage, advances, penalties), or is pay purely
   additive per completed order? (§5)
5. Will Khaalo offer rider insurance (accidental/medical), matching a real
   retention-relevant benefit both major platforms advertise? (§7)
6. Which of the §7 supporting screens are v1 vs later phases — recommend treating
   Earnings breakdown, Safety/SOS, and Support/Help as higher priority than Referral/
   Leaderboard-style features, but confirm with Anurag.
