# OpenCode Agent Prompt — Sponsored Ads Module (Khaalo/Welo)

Paste this whole prompt into your OpenCode agent as the task brief.

---

## Role & Context

You are a senior full-stack engineer working on **Khaalo (Welo)** — a Flutter-first,
multi-sided food ordering platform with:
- **Customer App** (Flutter)
- **Restaurant App** (Flutter — used by restaurant owners/staff)
- **Admin Panel** (Vue 3 web)
- **Backend**: Laravel 13 microservices

Your task: build a **Sponsored Ads module** in the Restaurant App with two tabs —
**Create Ads** and **Track Ads** — plus the backend recommendation engine, ad-serving
(auction) engine, Customer App ad placements, and Admin Panel oversight tools.

This module touches billing/money (ad spend), ranking (what customers see), and
attribution (which orders came from ads) — build the data model and event tracking
carefully since performance numbers and charges must be auditable.

Build order: **DB schema → event tracking (impressions/clicks/orders) → recommendation
service → ad-serving/auction service → Restaurant App Create Ads tab → Track Ads tab →
Admin Panel oversight → Customer App ad placements.**

---

## 1. Tab 1 — Create Ads

### 1.1 Ad Types
- **PPC (Pay-Per-Click)** — Placement sub-field (multi-select or single-select — confirm):
  `Listings`, `Search`, `Both`. Charged per click.
- **Card Ads** — served as a "Featured Restaurants" card/carousel placement.

**Open question — confirm before building:** is Card Ads also billed per-click (using
the same Cost Per Click field), or is it a flat/duration-based charge (e.g. ₹X per week
for a featured slot) since "featured restaurant" placements are usually fixed-inventory
rather than auction-per-click? The field list below assumes CPC applies to both per the
brief, but flag this explicitly to Anurag — it changes the billing model significantly.

### 1.2 Fields (shown after Ad Type is selected)

| Field | Type | Rules |
|---|---|---|
| Outlet | dropdown | required, restaurant's own outlets only |
| Start Date | date | required |
| Duration | numeric (weeks) | 1–8 weeks; auto-compute End Date = Start Date + duration |
| Weekly Budget | numeric (₹) | required; show recommendation (see §2) |
| Cost Per Click | numeric (₹) | required for PPC; see open question above for Card Ads; show recommendation (see §2) |
| Target Customers | dropdown | `All Users` / `New Users` |
| Time of Day | From–To time range + "All Day" toggle | restricts ad serving to this daily window |

### 1.3 Recommended additions not in original brief (flag, don't silently add)
- Ad approval/moderation step before an ad goes live (common on ad platforms to prevent
  policy violations) — confirm if required for v1 or if ads go live immediately on save.
- Card Ad creative — does the restaurant supply a custom image/banner for the featured
  card, or does it auto-pull from the outlet's existing profile photo? Not specified.

---

## 2. Budget & CPC Recommendation Engine

Build a recommendation service that suggests a CPC range and a weekly budget range,
shown inline next to those fields at ad-creation time (e.g. "Recommended CPC: ₹3–₹6").

**Suggested design:**
- **Inputs**: outlet category/cuisine, outlet's city/area, selected placement
  (Listings/Search/Both/Card), selected Target Customers, selected Time of Day, and
  platform-wide historical auction data for comparable ads (same category + geography +
  placement) over a trailing window (e.g. last 30 days).
- **CPC recommendation** = a percentile band (e.g. 25th–75th percentile) of the winning
  Rank Score / effective CPC among comparable recent ads, adjusted for:
  - Time-of-day demand (peak meal hours cost more — build a demand-multiplier curve)
  - New-user targeting (often has different competition/value than All Users — direction
    depends on your business model; confirm whether new-user acquisition should bid
    higher or lower than the general pool)
  - Category competitiveness (more advertisers in that cuisine/area category = higher
    recommended CPC)
- **Weekly budget recommendation** = expected available click/impression volume in the
  selected time-of-day window × recommended CPC × expected fill rate, with a floor
  ensuring the restaurant can realistically gather enough clicks/orders to evaluate
  performance (e.g. minimum viable spend for ~30-50 clicks/week — confirm target with
  Anurag).
- Cold-start handling: for a brand-new category/area with no historical auction data,
  fall back to a platform-wide default band and clearly label it as a "starter estimate."
- Expose this as its own backend endpoint (`GET /ads/recommendations?...`) so both
  Restaurant App and Admin Panel can reuse it.

---

## 3. Ad Serving / Auction Engine

Build a real serving algorithm, not a placeholder — this determines what customers see
and what restaurants get charged.

### 3.1 PPC (Listings / Search)
- Use a **modified Generalized Second-Price (GSP) auction**, similar to standard
  search-ads models:
  - `Rank Score = CPC bid × Predicted CTR × Relevance/Quality Score`
    - Predicted CTR: model or heuristic based on the ad/outlet's historical CTR (cold
      start = category average).
    - Relevance/Quality Score: for Search placement, match the outlet/menu against the
      search query; for Listings placement, match against category/location relevance.
  - Ads are ranked by Rank Score; top N eligible ads (cap ad density — e.g. max 2–3
    sponsored results per page/search) are shown, clearly labeled "Sponsored."
  - **Charge per click** = (next-highest Rank Score ÷ own Quality Score) + smallest
    increment, capped at the advertiser's own CPC bid (standard GSP pricing) — this
    means advertisers rarely pay their full bid.
- **Budget pacing**: implement pacing so a weekly budget doesn't exhaust in the first
  hour — throttle serving probability based on (budget remaining ÷ time remaining in the
  active window), recalculated periodically (e.g. hourly).
- **Eligibility filtering before entering the auction**: outlet must be within an active
  (not paused/completed/scheduled-future) ad, current time within the ad's Time-of-Day
  window, current date within Start/End Date range, and viewing customer must match
  Target Customers segment (All/New).
- **Frequency capping** (recommended addition, confirm): avoid showing the same ad to
  the same customer more than N times per session/day.
- **Click fraud protection** (recommended addition, confirm): dedupe rapid repeat clicks
  from the same device/session within a short window before charging.

### 3.2 Card Ads (Featured Restaurants)
- Fixed, limited inventory (a set number of featured slots on relevant screens) — this
  is an allocation/rotation problem, not a page of unlimited sponsored results.
- Recommend: **weighted rotation** — each eligible Card Ad gets rotation frequency
  proportional to its effective score (bid/budget-pacing-adjusted, similar to §3.1),
  ensuring fair exposure so one large-budget advertiser doesn't permanently occupy every
  slot. Still respect Target Customers, Time of Day, and active-date-range filtering.
- If Card Ads turn out to be flat-fee (per the open question in §1.1) rather than
  per-click, the "score" becomes budget/duration-weighted instead of CPC-weighted —
  confirm before implementing.

### 3.3 Event Tracking (needed for both serving and Track Ads tab)
- Log **impressions**, **clicks**, and **attributed orders** per ad, per day, with
  outlet/ad/customer-segment dimensions — this feeds the Track Ads graphs, the
  Performance tab, and billing.
- Order attribution rule: define and document the attribution window (e.g. order placed
  within X minutes of an ad click, same session) — confirm the window with Anurag.

---

## 4. Tab 2 — Track Ads

### 4.1 Filters
- Outlet filter (specific outlet or All)
- Status filter: `All / Active / Paused / Completed / Scheduled`

**Status enum inconsistency to resolve:** the brief lists card status as "active/
inactive/paused/completed" but the filter list uses "Active/Paused/Completed/Scheduled"
(no "Inactive"). Use **one canonical enum**: `Scheduled, Active, Paused, Completed` —
confirm whether "Inactive" is meant to be the same as `Paused`, or a distinct state (e.g.
auto-paused due to low wallet balance) that should be its own status.

### 4.2 Ad Card (list view)
| Element | Source |
|---|---|
| Heading | Ad Type (e.g. "PPC — Search", "Card Ad") |
| Ad ID | auto-generated, shown below heading |
| Outlet ID | |
| Duration | From – To dates |
| Sales from Ads | ₹ revenue attributed to this ad (from order attribution, §3.3) |
| Ad Spend | ₹ spent so far |
| Ad Orders | count of attributed orders |
| Status | badge: Scheduled / Active / Paused / Completed |
| Buttons | `View Details`, `Performance` |

### 4.3 View Details screen
Shows everything on the card, plus:
- Graph of orders — daily and weekly view (toggle), using the event data from §3.3
- Target Customers (All/New)
- Time of Day window
- **Created By** — restaurant's own name if created by the restaurant, or the Khaalo
  team member's name if created internally on the restaurant's behalf (requires a
  polymorphic `created_by` reference: restaurant user vs internal ops user — see §5 for
  the internal-creation flow this implies)
- **Help button** — raises a support ticket linked to this ad and lets the restaurant
  track its status. Confirm whether Khaalo already has a support-ticket system elsewhere
  in the platform to hook into, rather than building a new one from scratch.

### 4.4 Performance screen (content not fully specified in the brief — proposed, confirm)
Recommend showing, in addition to the View Details data:
- CTR (clicks ÷ impressions)
- Conversion rate (orders ÷ clicks)
- ROAS / cost per order (Sales from Ads ÷ Ad Spend, and Ad Spend ÷ Ad Orders)
- Spend pacing chart — actual spend vs planned weekly budget, day by day
- Click/impression trend graph
- Optional: percentile comparison vs platform average for similar outlets, to help the
  restaurant judge if their ad is performing well

---

## 5. Admin Features (Admin Panel — Vue 3)

- Central dashboard: view/filter all ads across all restaurants/outlets by status, ad
  type, restaurant, date range.
- Approve/reject/pause any ad centrally (if moderation is confirmed as required, §1.3).
- Global guardrails: min/max CPC floor and ceiling, max Card Ad slot inventory, max
  allowed duration (already capped at 8 weeks per outlet-facing rule, but confirm if
  admin can override), category/content policy blackouts.
- Platform-wide ad revenue and billing reports.
- Manage support tickets raised from the Track Ads help button (assign, resolve, SLA
  tracking).
- Fraud/anomaly monitoring — surfaced suspicious click patterns for manual review.
- **Internal ad creation** — Khaalo team members can create an ad on behalf of a
  restaurant/outlet; this ad's `Created By` must store the staff member's identity so it
  displays correctly in the restaurant's Track Ads view (§4.3).

---

## 6. Other Supporting Features (needed to make this a real, billable product)

- **Billing/wallet**: restaurant needs a funding mechanism for weekly budget —
  prepaid ad wallet, auto-debit from restaurant payouts, or invoice-based postpaid.
  Confirm which model Khaalo uses elsewhere in the platform and reuse it if one exists.
- **Low-balance handling**: auto-pause ads and notify the restaurant if wallet/budget
  runs out mid-week.
- **Notifications**: ad going live, budget exhausted, ad ending soon, ad auto-paused,
  performance milestones (e.g. "your ad crossed 100 orders").
- **Mid-flight editing rules**: define what can be changed after an ad has started (e.g.
  weekly budget and CPC editable; placement/outlet locked). Confirm with Anurag.
- **Invoice/billing history** export for restaurant accounting.
- **Sponsored labeling**: every PPC and Card Ad placement in the Customer App must be
  clearly marked "Sponsored"/"Ad"/"Featured" for transparency and (in most jurisdictions)
  legal compliance.

---

## 7. Customer App — Ad Placements

- PPC `Listings`/`Search` ads appear inline within organic results, ranked per §3.1,
  clearly labeled as sponsored, capped in density per page.
- Card Ads appear in a dedicated "Featured Restaurants" carousel/section, rotated per
  §3.2.
- Every render must log an impression event; every tap must log a click event, both
  feeding §3.3 and, for PPC, triggering the CPC charge per the GSP pricing rule.

---

## 8. Validation Summary

- Duration: 1–8 weeks
- Weekly Budget: > 0, ideally within the recommended band (warn, don't hard-block, if
  outside it)
- CPC: > 0, within admin-configured floor/ceiling (§5)
- Start Date: today or future (future start → `Scheduled` status until it begins)
- Outlet, Ad Type required; Placement required for PPC

---

## 9. Deliverables Checklist for the Agent

- [ ] DB migrations: `Ad`, `AdPlacement` (PPC sub-type), `AdPerformanceDaily`,
      `AdSupportTicket`, wallet/billing tables as applicable
- [ ] Event tracking: impression, click, attributed-order logging
- [ ] Recommendation engine endpoint (§2) + reuse in Restaurant App and Admin Panel
- [ ] Ad-serving/auction service (§3) with budget pacing, eligibility filtering, GSP
      pricing for PPC, and rotation logic for Card Ads
- [ ] Restaurant App: Create Ads tab (both ad types, full field set)
- [ ] Restaurant App: Track Ads tab — filters, ad cards, View Details, Performance
- [ ] Support ticket flow from the Help button
- [ ] Admin Panel: dashboard, moderation, guardrails, billing reports, internal ad
      creation with creator attribution
- [ ] Customer App: sponsored placements in Listings/Search + Featured Restaurants
      carousel, with impression/click logging
- [ ] Billing/wallet integration (confirm and reuse existing platform mechanism if any)
- [ ] Flag all open questions below to Anurag before finalizing that part of the logic

---

## Open Questions to Resolve Before/During Build (do not silently assume)

1. Card Ads billing model — CPC-based like PPC, or flat/duration-based (§1.1).
2. Canonical status enum — resolve "Inactive" vs "Paused" (§4.1).
3. Ad approval/moderation — required before an ad goes live, or immediate? (§1.3)
4. Card Ad creative — restaurant-supplied image, or auto-pulled from profile? (§1.3)
5. Mid-flight editing rules — which fields are editable after an ad starts? (§6)
6. Billing/funding model — prepaid wallet vs auto-debit vs invoice (§6) — reuse existing
   platform mechanism if one already exists.
7. Order attribution window — how long after a click does an order still count? (§3.3)
8. Support ticket system — new build, or integrate with an existing one? (§4.3)
9. New-user CPC direction — should New Users targeting recommend higher or lower CPC
   than All Users, per Anurag's acquisition economics? (§2)
