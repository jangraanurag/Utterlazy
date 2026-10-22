# OpenCode Agent Prompt — Menu Item Module Upgrade (Khaalo/Welo)

Paste this whole prompt into your OpenCode agent as the task brief.

---

## Role & Context

You are a senior full-stack engineer working on **Khaalo (Welo)** — a Flutter-first,
multi-sided food ordering platform with:
- **Customer App** (Flutter)
- **Restaurant App** (Flutter — used by restaurant owners/staff)
- **Rider App** (Flutter)
- **Admin Panel** (Vue 3 web)
- **Backend**: Laravel 13 microservices

Your task: upgrade the **"Add/Edit Menu Item"** feature in the Restaurant App with a
richer data model (variants, add-ons, tax, packaging charges, image gallery), and make
sure the resulting data is correctly consumed and displayed in the Customer App and
Rider App wherever menu items currently appear.

Treat this as a schema + API + multi-app UI change, not just a form change. Work in this
order: **DB schema → backend API/validation → pricing calculation service → Restaurant
App form → Customer App consumption → Rider App consumption → migration of existing data.**

---

## 1. Data Model Changes

### 1.1 Category (already global, admin-owned — extend if needed)
- Categories are created by **Admin**, not by restaurants.
- They power the Customer App home page category list.
- Restaurant's "Item Category" dropdown must pull from this **global** list.
- Always append a fixed, non-editable **"Other"** option at the end of the dropdown for
  items that don't fit any admin category. Decide and document how "Other" items are
  treated on the customer-app home page category browse (recommend: shown under an
  "Other" section, not hidden).

### 1.2 MenuItem — new/changed fields

| # | Field | Type | Rules |
|---|-------|------|-------|
| 1 | Item Name | text input | required, max 100 chars |
| 2 | Item Description | textarea | required, max 500 chars |
| 3 | Category | dropdown (single-select) | required, sourced from global Category table + "Other" |
| 4 | Food Preparation Type | dropdown (single-select) | required. Enum: `Veg, Non-Veg, Vegan, Jain, Vaishno, Egg` |
| 5 | Fasting Suitable | checkbox | boolean, indicates item can be consumed during religious fasting (vrat) |
| 6 | Base Price | numeric | required, > 0 |
| 7 | Tax Type | dropdown | required. Enum: `GST_5 (Service)`, `No GST`, `MRP / Packaged Item` (confirmed rule set, replaces earlier draft). `GST_5 (Service)` — 5% GST on cooked/prepared food, only selectable if the restaurant has GST registration on file. `No GST` = restaurant not GST-registered. `MRP / Packaged Item` = packaged goods sold at a fixed MRP — capture a restaurant-entered embedded GST rate (e.g. 5/12/18%, varies by product) for reporting only; it's already included in the MRP and never added at checkout. |
| 8 | Tax Included | toggle (Yes/No) | Yes = Base Price is tax-inclusive. No = tax is added on top of Base Price at checkout. **Forced to and locked at Yes when Tax Type = `MRP / Packaged Item`**, since MRP is tax-inclusive by definition. |

### 1.3 Variants
- New table: `VariantType` — restaurant selects one type per item from: `Size (in/cm)`,
  `Volume (ml/ltr)`, `Quantity (e.g. Half Plate / Full Plate)`.
- New table: `Variant` — child rows under a MenuItem+VariantType, each with:
  - label (e.g. "Half Plate", "Full Plate", "8 inch")
  - unit (inherited from variant type, e.g. inch/cm or ml/ltr — not applicable for Quantity)
  - `price_impact` (signed number, can be 0 or negative, e.g. Half Plate = 0, Full Plate = +50)
  - one variant per item should be flagged `is_default` for default cart pricing.
- Final variant price = `Base Price + price_impact` of the selected variant.
- A MenuItem can have **at most one active VariantType** with multiple Variant rows
  under it (confirm with Anurag if multiple variant axes per item — e.g. Size AND
  Volume together — should ever be supported; default to **single axis** for v1).

### 1.4 Add-ons
- New table: `AddOnGroup` — created independently by the restaurant (not per menu item).
  Each group has a name (e.g. "Extra Toppings", "Extra Sauces") and contains multiple
  `AddOnItem` rows, each with its own `price` and its own `TaxType` (same enum as 1.2 #7).
- Menu Item form: a multi-select or single-select (confirm which) to **attach existing
  AddOnGroup(s)** to the item. Restaurant must be able to manage AddOnGroups from a
  separate screen (create/edit/delete groups and their items) reachable from the menu
  module.
- At order time, addon price + addon's own tax are calculated independently of the
  item's own tax settings.

### 1.5 Packaging Charge
- New config per MenuItem (item-level) and a separate **order-level default** config
  (restaurant settings, applies when no item-level override exists).
- Fields:
  - **Level**: `Order Level` or `Item Level`
  - **Charge Type**:
    - Item Level → **Fixed value only**
    - Order Level → **Fixed value or Percentage**
  - **Value**: numeric
  - **Tax on packaging charge**: dropdown — `GST_5` or `None` (packaging charge tax is
    independent of the item's own Tax Type)
- Business rule to implement: if an item has its own item-level packaging charge, does
  it override the order-level charge for that item, or do both apply? **Confirm with
  Anurag before building — recommend item-level overrides order-level for that specific
  item.**

### 1.6 Image Gallery
- Up to **5 images** per MenuItem.
- One image must be flagged as **cover/front image** (shown in listing views).
- Restaurant can **reorder** images (drag-and-drop or up/down controls) — persist order.
- Validate file type/size per your existing media upload service conventions.

### 1.7 Serving Info
- Two fields:
  1. **Serves (no. of people)** — numeric, e.g. "Serves 2"
  2. **[NEEDS CONFIRMATION FROM ANURAG — second field not fully specified in the
     original brief. Do not guess and ship silently.]** Likely candidates to ask about:
     preparation/wait time, spice level, or a free-text serving note. **Stop and ask
     the product owner before implementing this second field**, or implement field 1
     only and leave a clearly marked TODO for field 2.

---

## 2. Pricing Calculation Logic (backend service — single source of truth)

Implement one shared pricing service used identically by Customer App checkout and
Rider App order display, so numbers never drift between apps.

1. `item_price = Base Price + selected_variant.price_impact`
2. If `Tax Included = Yes`: `item_price` already contains tax; back-calculate the tax
   component for invoicing/reporting only — customer pays `item_price` as-is.
   If `Tax Included = No`: `item_tax = item_price * tax_rate(Tax Type)`, added at checkout.
3. For each selected add-on: `addon_total = addon.price`; addon tax computed the same
   way using the add-on's own Tax Type. **Confirm with Anurag whether add-ons ever have
   a "tax included" toggle like items do, or are always tax-added-extra — not specified
   in the brief. Default assumption: tax-added-extra.**
4. Packaging charge: resolve item-level override if present, else order-level default;
   compute fixed or percentage (percentage applies to order/item subtotal — confirm
   base); apply its own tax if `GST_5` selected.
5. Order total = Σ(item_price + item_tax) + Σ(addon_total + addon_tax) + packaging_charge
   + packaging_tax (+ delivery/other existing charges).

Write unit tests covering: tax-included vs tax-excluded, GST_5 vs GST_18 vs NONE,
item-level vs order-level packaging, fixed vs percentage packaging.

---

## 3. Cross-App Reflection Requirements

| Field | Customer App | Rider App |
|---|---|---|
| Name, description, images, cover image | Item listing + item detail page | Order item summary (name + cover image) |
| Category | Home page category browse/filter | — |
| Prep type, fasting flag | Filter chips (Veg/Non-veg/etc, "Fasting friendly") on listing + detail | Optional, for rider's own reference if useful |
| Price, tax, tax-included | Item price shown correctly inclusive/exclusive; cart & checkout breakdown shows tax line only when not included | Order summary shows final item price, not raw tax breakdown |
| Variants | Variant selector on item detail before add-to-cart; selected variant shown in cart | Selected variant shown in order item line |
| Add-ons | Add-on selector on item detail; selected add-ons + their price shown in cart | Selected add-ons shown in order item line |
| Packaging charge | Shown as a distinct line in cart/checkout summary, not silently merged into item price | Not typically shown, confirm if rider needs to see it for COD reconciliation |
| Serving info | Shown on item detail page | — |

Audit every existing screen in Customer App and Rider App that currently reads MenuItem
fields (search for existing item-detail, cart, checkout, order-summary components) and
update them — don't just add new fields to the API and leave old UI unaware of them.

---

## 4. Migration & Backward Compatibility

- Existing menu items have none of these new fields populated. Define sensible defaults:
  - `Tax Type = NONE`, `Tax Included = No` unless a global default exists already.
  - No variants → item behaves as single-price item (unchanged current behavior).
  - No add-ons attached → no add-on selector shown.
  - No packaging config → fall back to a platform-level default (confirm value).
  - No serving info → hide that section rather than showing empty fields.
- Write a migration script and confirm it doesn't break active orders mid-flight.
- Existing restaurants should not be forced to fill all new fields before their current
  menu items keep working — make new fields optional at the DB level except where
  explicitly marked "required" above (those are required only for **new** items going
  forward, not retroactively enforced on old rows).

---

## 5. Validation Summary (Restaurant App form)

- Item Name: required, ≤100 chars
- Description: required, ≤500 chars
- Category: required
- Prep Type: required
- Base Price: required, numeric, >0
- Tax Type: required (default NONE)
- At least one variant required only if a VariantType is selected
- Image gallery: 0–5 images, exactly one marked cover if any image uploaded
- Packaging charge value required only if packaging is enabled for that level

---

## 6. Deliverables Checklist for the Agent

- [ ] DB migrations for all new/changed tables
- [ ] Backend API endpoints (create/update/list/detail) + validation
- [ ] Shared pricing calculation service + unit tests
- [ ] Restaurant App: Add/Edit Menu Item form (all fields above)
- [ ] Restaurant App: AddOnGroup management screen (separate from menu item form)
- [ ] Restaurant App: packaging charge settings (order-level defaults)
- [ ] Customer App: updated item listing, item detail, cart, checkout to reflect all fields
- [ ] Rider App: updated order/item summary to reflect all fields
- [ ] Data migration script + backward-compatibility defaults
- [ ] Flag the two open questions above to Anurag before finalizing that part of the logic

---

## Open Questions to Resolve Before/During Build (do not silently assume)

1. Serving Info — second field definition (see §1.7).
2. Add-ons — is item-level "Tax Included" behavior applicable to add-ons too?
3. Packaging charge — item-level override vs stacking with order-level default.
4. Menu item ↔ AddOnGroup — single group or multiple groups per item?
5. Variants — single variant axis per item only, or multiple (e.g. Size + Volume together)?
