# OpenCode Agent Prompt — Offers/Promotions Module (Khaalo/Welo)

Paste this whole prompt into your OpenCode agent as the task brief.

---

## Role & Context

You are a senior full-stack engineer working on **Khaalo (Welo)** — a Flutter-first,
multi-sided food ordering platform with:
- **Customer App** (Flutter)
- **Restaurant App** (Flutter — used by restaurant owners/staff)
- **Admin Panel** (Vue 3 web)
- **Backend**: Laravel 13 microservices

Your task: build an **Offers/Promotions module** with three distinct offer types —
Promo Codes, BOGO Discounts, and Admin Opt-In Campaigns — creatable/manageable from the
Restaurant App (and Admin Panel for opt-in campaigns), consumed at Customer App
checkout, and priced correctly alongside the existing pricing service (base price, tax,
variants, add-ons, packaging — from the menu module).

**Assumption stated up front:** although only "restaurant app ya web" was mentioned in
the brief, offers are inherently customer-facing (redeemed at checkout) and admin-facing
(for opt-in campaigns), so this scope includes Customer App checkout logic and Admin
Panel campaign management. Flag if this should be narrower.

Build order: **DB schema → discount calculation service → Restaurant App create/manage
UI → Admin Panel opt-in campaign UI → Customer App promo page + checkout application.**

---

## 1. Promo Codes

### 1.1 Fields

| Field | Type | Rules |
|---|---|---|
| Promo Code | text | required, unique (confirm scope: unique per-restaurant or platform-wide) |
| Discount Type | dropdown | `Flat Amount` or `Percentage` — **see assumption below** |
| Discount Value | numeric | required; ₹ if Flat, % if Percentage |
| Max Discount Cap | numeric + "No Max Limit" toggle | only meaningful for Percentage type; when Flat, cap is implicitly the flat value itself |
| Min Purchase Amount | numeric + "No Min Purchase" toggle | applies to both discount types |
| User Type | dropdown | `All Users` or `New Users` (confirm definition of "new" — e.g. zero prior orders on platform, or zero prior orders at this restaurant) |
| Time of Day | From–To time range + "All Day" toggle | restricts redemption to a daily window |
| Start Date | date, required | |
| End Date | date + "No End Date" toggle | open-ended promo if toggled |

**Assumption on Discount Type (confirm with Anurag):** the brief said "discount in %"
but gave two examples — a flat-amount example (₹60 off on min purchase ₹150) and a
percentage example (flat 20% off, capped at ₹120). Implemented as a `Flat Amount /
Percentage` dropdown so both examples are valid configurations. If the intent was
percentage-only, drop the Flat Amount option and simplify the cap field to always show.

### 1.2 Recommended additions not in original brief (flag, don't silently add)
- Usage limit per user (e.g. once per user) and/or total redemption cap — common in
  promo systems, confirm if needed for v1.
- Status field (Active/Inactive/Expired) — likely auto-derived from dates but confirm
  if restaurants need a manual pause toggle independent of dates.

---

## 2. BOGO Discounts

### 2.1 Fields
- **Buy Quantity** — numeric (e.g. 1)
- **Get Free Quantity** — numeric (e.g. 1, 2, or 3 — "buy one get one/two/three free")
- **Applicable Menu Items** — multi-select from restaurant's menu

### 2.2 Open question — confirm before building
The brief says "with selected menu items" (plural) but doesn't clarify the matching
logic. Two possible interpretations:
1. **Same-item BOGO**: buy N of item X, get M of the *same* item X free.
2. **Set-based BOGO**: buy N from a selected item list, get M free from that same list
   (any combination), or from a *separate* "get" list.

Default to interpretation (1) — same-item — as it's the standard BOGO pattern, but do
not hardcode assumptions into the schema in a way that blocks adding (2) later.

### 2.3 Scheduling
**Assumption:** BOGO reuses the same `User Type`, `Time of Day`, `Start Date`/`End Date`
(with No End Date) fields as Promo Codes, for consistency across offer types. Confirm.

### 2.4 Pricing interaction
Free item(s) must show as ₹0 line items in cart/bill breakdown (not silently merged into
the paid item's price), and coordinate with the existing pricing service from the menu
module for how tax/packaging is computed on a free item (typically: no tax on the ₹0
portion, but confirm with Anurag / your tax advisor for compliance).

---

## 3. Admin Opt-In Campaigns

### 3.1 Admin Panel (Vue 3) — Campaign Creation
- Campaign name (e.g. "Diwali Sale", "Rakhi Sale")
- Discount configuration (reuse Flat/Percentage + cap + min purchase fields from §1.1)
- **Discount Share** — how the discount cost is split between platform and restaurant
  (e.g. percentage split: platform bears X%, restaurant bears Y%). This needs a finance/
  settlement note field or link to your existing settlement/reconciliation system —
  confirm how this feeds into restaurant payouts.
- Start Date / End Date
- List of eligible restaurants (or open to all, restaurant self-selects via opt-in)

### 3.2 Restaurant App — Opt-In Flow
- Restaurant sees a list of currently running admin campaigns they're eligible for.
- On tapping into a campaign, restaurant sees the **discount share breakdown** (what
  portion they're funding) before opting in.
- Simple opt-in/opt-out toggle per campaign, per restaurant.

### 3.3 Customer App — Visibility Rule
- A campaign (e.g. "Diwali Sale") only surfaces on the **promo page** for restaurants
  that have **actively opted in**. Non-opted restaurants must not show the campaign
  banner/badge at all, even if the campaign is globally active.
- Opting out mid-campaign should immediately stop the discount from applying at that
  restaurant's checkout — build this as a live eligibility check at checkout, not a
  cached flag.

---

## 4. Discount Calculation Service (shared, single source of truth)

Extend (don't duplicate) the pricing service built for the menu module:

1. Compute cart subtotal from items (with variants/add-ons) as already implemented.
2. Check promo code / BOGO / opt-in campaign eligibility in this order (define and
   confirm precedence if a customer somehow qualifies for more than one — recommend:
   **only one offer applicable per order**, best-value auto-selected or customer choice
   — confirm with Anurag):
   - Min purchase met?
   - User type eligible (All Users vs New Users)?
   - Current time within Time-of-Day window?
   - Current date within Start/End Date range?
   - For opt-in campaigns: is this restaurant currently opted in?
3. Apply discount: Flat amount subtracted, or Percentage applied then capped at Max
   Discount Cap if set.
4. For BOGO: identify eligible free item(s) in cart per §2.2 logic, zero their price,
   recompute tax/packaging accordingly.
5. Discount line must show clearly and separately in the order summary (not folded into
   item prices) across Restaurant App order view and Customer App checkout/receipt.

Write unit tests: min-purchase boundary, cap boundary, new-user vs all-user, time-of-day
edge (window crossing midnight?), no-end-date open promos, BOGO same-item math, opt-in
toggle-off mid-order.

---

## 5. Cross-App Reflection Requirements

| Feature | Restaurant App | Admin Panel | Customer App |
|---|---|---|---|
| Promo Codes | Create/edit/deactivate | Read-only oversight (recommend) | Promo page listing + apply-code-at-checkout field + validation errors (expired, min purchase not met, etc.) |
| BOGO | Create/edit/deactivate, select items | — | Shown as a badge on eligible menu items + auto-applied free item in cart |
| Opt-In Campaigns | Opt-in/opt-out, view discount share | Create campaign, set discount share, view opted-in restaurants | Promo page shows campaign only for opted-in restaurants; badge on restaurant listing |

---

## 6. Validation Summary

- Promo Code: unique, required; Discount Value > 0; End Date (if set) ≥ Start Date;
  Time-of-Day To must logically follow From (or explicitly support overnight windows —
  confirm)
- BOGO: Buy Quantity ≥ 1, Get Free Quantity ≥ 1, at least one menu item selected
- Opt-In Campaign: Admin-only creation; Restaurant opt-in requires acknowledging the
  discount share before activating

---

## 7. Deliverables Checklist for the Agent

- [ ] DB migrations: `PromoCode`, `BogoDiscount` (+ item mapping), `OptInCampaign`,
      `RestaurantCampaignOptIn`
- [ ] Backend API: CRUD for all three offer types + eligibility/validation endpoint
- [ ] Discount calculation service extending the existing pricing service + unit tests
- [ ] Restaurant App: Promo Code create/edit screen
- [ ] Restaurant App: BOGO create/edit screen
- [ ] Restaurant App: Opt-in campaign list + opt-in/opt-out flow with discount-share display
- [ ] Admin Panel: Campaign creation screen with discount-share configuration
- [ ] Customer App: Promo page (lists active, eligible offers per restaurant)
- [ ] Customer App: Checkout — apply promo code field, auto-apply BOGO/campaign, discount
      line item in order summary
- [ ] Flag all open questions below to Anurag before finalizing that part of the logic

---

## Open Questions to Resolve Before/During Build (do not silently assume)

1. Discount Type — Flat Amount vs Percentage vs both
2. Promo code uniqueness — per-restaurant or platform-wide.
3. "New User" definition — platform-wide new, or new to this specific restaurant.
4. BOGO matching logic — same-item 
5. Whether a customer can stack multiple offers on one order, or only one at a time.
6. Time-of-day windows crossing midnight — supported or not.
7. Discount share settlement — how it feeds into restaurant payout/reconciliation.
