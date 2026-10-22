# OpenCode Agent Prompt — Reporting Module (Khaalo/Welo)

Paste this whole prompt into your OpenCode agent as the task brief. This one is
grounded directly in your own uploaded reference material — a real Zomato Partner
"Business Reports" CSV export and two screenshots of the actual Reporting UI — not just
general research. Use the exact metric taxonomy in §2 as the authoritative spec; it was
extracted directly from that CSV.

---

## Role & Context

You are a senior full-stack engineer working on **Khaalo (Welo)**:
- **Customer App** (Flutter)
- **Restaurant App** (Flutter)
- **Admin Panel** (Vue 3 web)
- **Backend**: Laravel 13 microservices

Your task: build **Reporting**, with an outlet filter and two tabs — **Live Tracking**
and **Business Reports** — giving restaurants proper analytical visibility into their
sales, customer experience, funnel, ads, and offers performance, with graphs, not just
raw numbers.

Build order: **Metrics fact-table design & nightly aggregation job (§5) → Live Tracking
tab (§3) → Business Reports tab with trend table + Generate Report export (§4) → item-
level/dish-trend data source (§3.4) → Admin Panel + AM Workspace views.**

---

## 1. Navigation

`Reporting` opens with an **outlet filter** (reuse the search/select pattern from the
Payout and Invoices modules), then two tabs:

1. Live Tracking
2. Business Reports

**Note on naming vs the reference screenshots:** in the real Zomato Partner Hub, "Live
Tracking" and "Business Reports" are the two actual tab names, but the weekly trend
table with sparkline graphs you'll see in a live Zomato account actually lives under
their "Business Reports" tab, not "Live Tracking" (which is more likely a real-time
today's-orders view). **This document builds what you specified** — your own "Live
Tracking" tab is the weekday-comparison analytics dashboard described in §3, and your
"Business Reports" tab is the trend-table-plus-export tool described in §4. Flag this
naming choice to Anurag once, just so it's a deliberate decision and not a
mixed-up label carried over from the reference screenshots.

---

## 2. Metric Taxonomy (extracted directly from your uploaded CSV — build to this exactly)

The real export is a wide table: one row per (Restaurant, Overview category, Metric),
with one column per day/week/month in the selected range. Here is the full taxonomy,
verbatim from your data:

| Overview category | Metrics |
|---|---|
| **Sales** | Delivered orders, Sales (Rs), Market share (%) |
| **Customer experience** | Average rating, Rated orders, Bad orders, Rejected orders, KPT+10 delayed orders, Poor rated orders, Total complaints, Non-refunded complaints, Total complaints – Poor packaging, Total complaints – Poor quality, Total complaints – Wrong order, Total complaints – Missing items, Self logs other ors, Lost sales (Rs), Online %, Offline time (in hours), KPT (in minutes), FOR accuracy (%) |
| **Customer funnel** | Impressions, Impressions to menu (%), Menu opens, Menu to cart (%), Cart builds, Cart to orders (%), Placed Orders |
| **Customer segmentation** | New user orders, Repeat user orders, Lapsed user orders, Breakfast orders, Lunch orders, Snacks orders, Dinner orders, Late night orders |
| **Ads** | Sales from ads (Rs), Ads CTR (%), Ads impressions, Ads menu opens, Ads orders, Ads spend (Rs), Ads ROI |
| **Offers** | Orders with offers, Gross sales from offers (Rs), Discount given (Rs), Effective discount (%) |

**Average Order Value (AOV)** is not a raw metric in this data — it's a **derived**
display value: `Sales (Rs) ÷ Delivered orders` for the period. Compute it, don't store
it as a separate fact.

"Customer segmentation" (New/Repeat/Lapsed users, meal-time buckets) wasn't explicitly
requested in the brief but is part of the real reference data — recommend including it
in Business Reports (§4) even if Live Tracking doesn't surface it, since it's already
in the taxonomy and cheap to add once the underlying fact table exists.

---

## 3. Tab 1 — Live Tracking

### 3.1 Weekday-comparison logic (the core mechanic you specified)
Always relative to **yesterday**: if yesterday was a Monday, show the **last 5
Mondays** (yesterday + the 4 Mondays before it) as the comparison set. This refreshes
daily — every day, "yesterday's weekday" and its 4 prior occurrences become the new
comparison set. This is a same-day-of-week seasonality view (e.g. "are Mondays trending
up or down"), distinct from a plain week-over-week or day-over-day view — build it as
its own query pattern (filter the fact table by day-of-week, take the 5 most recent
occurrences), not by slicing a generic date range.

Apply this same 5-occurrence comparison to every metric below, shown as a small trend
line/bar per metric plus the 5 data points in a compact table, so the restaurant sees
both the shape and the exact numbers.

### 3.2 Sections & recommended chart types

**Sales overview**
- Sales (Rs), Delivered Orders, AOV — line chart per metric across the 5 comparison
  points (or one combo chart with Sales as bars and AOV as a line overlay)

**Customer experience**
- Average rating — line/gauge
- **Bad Orders** — headline number, broken down by cause: Rejected, KPT+10 Delayed
  (matches your "delays"), Total Complaints (matches your "complaint"), Poor Rated
  (matches your "poor rated") — a stacked bar or donut breakdown per comparison point,
  since a restaurant needs to know *which* cause is driving bad orders, not just the
  count
- **Lost Sales (Rs)** — the reference data has a single "Lost sales (Rs)" figure and a
  separate "Offline time (in hours)" metric, but **no existing split between
  offline-caused and inventory/stockout-caused lost sales**. You explicitly asked for
  this split ("due to offline and inventory") — this requires **new instrumentation**:
  track item-marked-out-of-stock events and estimate lost sales attributable to items
  being unavailable, separately from the restaurant being fully offline. Flag this as a
  net-new build, not a mapping of existing data — Zomato's own export doesn't have this
  breakdown either.
- Online % — derived as (hours online ÷ total hours in period) × 100, using the same
  offline-time tracking

**Customer funnel**
- Impressions → Menu Opens → Cart Builds → Placed Orders, as a **funnel chart** with
  the conversion percentage labeled at each step (Impressions→Menu, Menu→Cart,
  Cart→Orders) — this is a classic 4-stage funnel visualization, use it rather than a
  bar chart so the drop-off at each stage is visually obvious

**Ads & Offers**
- Sales from Ads (Rs), Sales from Offers (Gross sales from offers, Rs) — bar chart
  comparing the two per period
- **Dish trends** — not present in the daily-metrics reference data at all (that CSV is
  restaurant-level, not item-level). This needs an **item-level sales aggregation**
  source — see §3.4.

### 3.3 Read from the pre-aggregated fact table
Compute all of the above from the `DailyOutletMetric` fact table (§5), not by scanning
raw orders on every page load — the weekday-comparison query pattern in particular
would be expensive to run live against raw order data at scale.

### 3.4 Dish Trends — new data requirement
"Dish trends" needs a per-item, per-day sales aggregate (item name, order count,
revenue, trend direction vs the prior comparable period) — this is the same underlying
data source that should power the "Item Sales Report" option in the Business Reports
tab's Generate Report flow (§4.3). Build one shared `DailyOutletItemMetric` fact table
(item_id, outlet_id, date, orders, revenue) and use it for both.

---

## 4. Tab 2 — Business Reports

### 4.1 Default view — trend table with sparklines
Matches your reference screenshot: a table with metric names as rows (grouped by
Overview category per §2) and time periods as columns, with a small trend-line
sparkline per metric row. Support toggling the column granularity between **Daily /
Weekly / Monthly** (same toggle as the reference UI), which changes what a "column"
represents — a day, an ISO week, or a calendar month.

### 4.2 Generate Report (export)
Reuse the reference UI pattern exactly:
- **Report View**: `Detailed report` (all metrics from §2, at the chosen granularity)
  or `Item sales report` (per-item breakdown, powers dish trends too — see §3.4)
- **Data View**: `Daily` / `Weekly` / `Monthly`
- **Duration**: `Last 7 days` / `Last 14 days` / `Last 30 days` / `Custom` (date range
  picker)
- **Outlet scope**: the module's outlet filter, or "All outlets" if the restaurant has
  more than one

### 4.3 Export file format — mirror the real structure
Your uploaded CSV's exact column layout is the right target format for the Detailed
report:

```
Restaurant ID, Restaurant name, Subzone, City, Overview, Metric, <date 1>, <date 2>, ...
```

One row per (Overview category, Metric) combination from §2, wide-format with one
column per period in the selected range/granularity. Build the Item Sales Report as a
parallel structure keyed by item instead of Overview/Metric (item name, item ID,
orders, revenue per period column).

### 4.4 Multi-outlet handling
When "All outlets" is selected, the reference CSV includes one full block of rows per
restaurant (see the sample data below) — replicate this: don't collapse multiple
outlets into a single aggregated row unless the restaurant explicitly wants a combined
view (flag as a possible future toggle, not required now).

Sample confirmed structure from your own account (3 outlets, same city):
`Restaurant ID 22369413 / Chinese Food`, `21888115 / JB Foods`, `22365201 / China
Street` — each with its own full Overview/Metric block.

---

## 5. Data Model & Aggregation Job (recommended — not explicit in the brief, but needed at scale)

- `DailyOutletMetric` — outlet_id, date, overview_category, metric_name, value —
  mirrors §2's taxonomy row-for-row. One row per metric per outlet per day.
- `DailyOutletItemMetric` — outlet_id, item_id, date, orders, revenue — powers dish
  trends (§3.4) and the Item Sales Report (§4.2).
- **Nightly (or more frequent, e.g. hourly) aggregation job** computes both fact tables
  from raw order/event data. Both Live Tracking and Business Reports query these fact
  tables, never raw orders directly — this keeps the weekday-comparison and multi-week
  trend queries fast regardless of how large the raw order history grows.
- Weekly/Monthly views in Business Reports (§4.1) are just a `GROUP BY` roll-up of the
  same daily fact table — don't build separate weekly/monthly aggregation jobs.

---

## 6. Cross-Panel Reflection

| Surface | What it needs |
|---|---|
| **Restaurant App/Web** | Full module as specified — this is the primary audience |
| **Admin Panel** | Same reporting, viewable for any restaurant/outlet, for internal analysis and support context |
| **AM Workspace** (from the earlier Account Manager prompt) | Same view scoped to assigned restaurants — ties into the AM's existing growth-advisory role (recommending menu/ads/offers changes based on what these reports show) |

---

## 7. Validation & Edge Cases

- Zero-activity days/outlets still get rows with 0 values (matches the reference data,
  which shows plenty of legitimate zero rows) — don't omit them, a restaurant needs to
  see "no orders that day," not a gap.
- Weekday-comparison logic must correctly handle a restaurant with less than 5 weeks of
  history (show however many occurrences exist, don't error or fake data).
- AOV must handle the zero-orders case (avoid divide-by-zero — show as 0 or "—", not an
  error).
- Multi-outlet export keeps each outlet as its own row block (§4.4).

---

## 8. Deliverables Checklist for the Agent

- [ ] `DailyOutletMetric` and `DailyOutletItemMetric` fact tables + aggregation job (§5)
- [ ] Live Tracking tab: weekday-comparison logic (§3.1), all 4 sections with correct
      chart types (§3.2), Bad Orders cause breakdown, new offline-vs-inventory lost-
      sales instrumentation
- [ ] Dish Trends data source shared with Item Sales Report (§3.4)
- [ ] Business Reports tab: trend table with sparklines + Daily/Weekly/Monthly toggle
      (§4.1)
- [ ] Generate Report modal matching the reference UI (§4.2) + export file matching the
      real CSV column structure (§4.3)
- [ ] Multi-outlet export handling (§4.4)
- [ ] Admin Panel + AM Workspace views (§6)
- [ ] Flag the naming/tab-content note (§1) and the lost-sales instrumentation gap
      (§3.2) to Anurag before finalizing

---

## Open Questions to Resolve Before/During Build (do not silently assume)

1. Confirm the Live Tracking / Business Reports tab-content split described in §1 is
   intentional (your own design, not meant to mirror Zomato's literal tab meanings).
2. How to compute "inventory-caused" lost sales specifically — needs a definition of
   what counts as a lost sale due to an out-of-stock item (e.g. item marked unavailable
   while a customer had it in an active session) before this can be built. (§3.2)
3. Should Customer Segmentation (§2, present in the real data but not in your brief) be
   included in Business Reports now, or held back for later since it wasn't requested.
4. Aggregation job frequency — nightly is the minimum recommendation; confirm if
   near-real-time (hourly) is needed for Live Tracking to feel "live." (§5)
5. Multi-outlet combined/roll-up view — needed now, or only the per-outlet breakdown
   shown in the reference data? (§4.4)
