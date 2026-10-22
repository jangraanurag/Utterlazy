# OpenCode Agent Prompt — Finance: Restaurant Payout Module (Khaalo/Welo)

Paste this whole prompt into your OpenCode agent as the task brief. This module is the
detailed, buildable version of §7 (Payout & Reconciliation) from the earlier
Operations/AM/Support-CRM master spec — read that section for context on how this
connects to Support tickets, the Grievance Officer, and Account Managers.

---

## Role & Context

You are a senior full-stack engineer working on **Khaalo (Welo)**:
- **Customer App** (Flutter)
- **Restaurant App** (Flutter)
- **Admin Panel** (Vue 3 web)
- **Backend**: Laravel 13 microservices

Your task: build the **Finance → Payout** module — restaurant/outlet search, the payout
screen with its filters and history, the Wednesday auto-generation job, the exact
calculation formula, the manual UTR-entry-to-Paid flow, multi-sheet Excel export, and the
corresponding views in Restaurant App/Web. This module moves real money — build it with
the same rigor as the bank-detail-change OTP-gating from the earlier prompts: audit
everything, make nothing a silent change.

**Research grounding, confirmed by Anurag:** Zomato's actual restaurant settlement
export is a multi-tab Excel file (Pay-out Breakup / Order-level / Addition-Deduction
Summary / Addition-Deduction Details tabs), and industry practice nets off platform
commission, GST on commission, payment-gateway fees, ad/promo charges, and refund
adjustments from gross sales — closely aligned with what you've specified. Anurag has
confirmed Khaalo's actual tax mechanics follow the CGST Section 9(5) e-commerce-operator
model: (1) GST is remitted by the **platform**, not the restaurant, for orders through
the app — Khaalo collects it from the customer and deposits it via its own GSTR-3B; the
restaurant only reports these supplies in **Table 14 of its own GSTR-1** for
reconciliation, it never deposits this money itself; (2) TDS under Section 194-O is
**not currently applicable** — build the schema field for it but do not make it an
active deduction in v1. Full confirmed rate logic is in §4.3.

Build order: **DB schema (ledger-based, not just weekly snapshots) → Wednesday
generation batch job → calculation engine → Admin Finance screens → UTR/Paid flow →
multi-sheet export → Restaurant App/Web payout views → staffing/process design.**

---

## 1. Navigation & Restaurant/Outlet Search Screen

`Finance → Payout` opens a search/select screen:
- Search bar (restaurant name, outlet name, or outlet ID)
- Results as a list of cards, each showing:
  - **Restaurant/Outlet Name** — bold
  - Below it: Locality, City, Outlet ID
- Tapping a card opens that restaurant/outlet's **Payout Screen** (§2)

**Open question — confirm before building:** is payout settled **per outlet**
(independent bank account, independent UTR per outlet) or **per restaurant**
(aggregated across all its outlets into one payout)? The brief's search screen lists
both restaurant and outlet, so this needs an explicit decision — it changes the data
model (§8) and the generation job (§3) significantly. Default assumption for this
document: **per outlet**, since bank details and performance vary by outlet — flag if
restaurant-level aggregation is actually intended.

---

## 2. Payout Screen

### 2.1 Filters
- Outlet filter (if viewing at restaurant level with multiple outlets)
- From Date / To Date — browses historical payout cycles, not just the current one

### 2.2 Layout
- **Current cycle card** (most recent generated cycle): cycle date range, status
  (`Pending` / `Paid` / see §6 for full status set), net payable amount
- **History list** (filtered by the date range above): one row per past cycle with
  cycle dates, net amount, status, UTR (once paid)
- Tapping any cycle opens its **full breakdown** (§4) and export options (§7)

---

## 3. Weekly Payout Generation (Batch Job)

- Runs automatically **every Wednesday at 8:00 AM**, for every restaurant/outlet.
- **Cycle window**: the previous week's **Monday 12:00 AM to Sunday 11:59 PM** (i.e. a
  Wednesday generation always covers the Mon–Sun that ended two to three days earlier).
- On generation, each cycle is created with status `Generated — Pending Payment` (not
  `Paid` — payment happens separately, §6).
- **Idempotency**: the job must not double-generate a cycle for the same
  restaurant/outlet + date window if it's re-run (e.g. after a retry) — check for an
  existing cycle record first.
- **Scale design**: process restaurants/outlets asynchronously (queued jobs per
  outlet), not as one blocking loop — at scale this must not risk timing out or
  partially failing silently. Add monitoring/alerting so Engineering/Ops is notified if
  the Wednesday run fails or is incomplete (see §9 staffing).
- **Zero-order cycles**: still generate a cycle (showing zero) for outlets with no
  orders that week, rather than skipping them — keeps the payout history continuous and
  avoids the ambiguity of "was this outlet just not evaluated?"

---

## 4. Payout Calculation Formula

### 4.1 Base and deductions/additions
```
Net Payable This Cycle =
    Order Value After Discount
  − Tax                                  (see §4.3 — confirm with CA)
  − Commission
  − Convenience Fee (or other collected platform fees)
  − Delivery Charges Collected            (see open question below)
  − Deductions (§4.2)
  + Additions (§4.2)
  + / − Previous Cycle Carried Balance    (see §5 — ledger, not a one-off line)
```

### 4.2 Deductions and Additions — itemized, not a single number
Every deduction and addition must be its own line item with a type, amount, and
reference (so it's auditable and disputable, not a mystery lump sum):

**Deductions:**
- Previous week's Sponsored Ads spend (from the Ads module)
- Any other company-charged deduction (packaging cost-share, SLA-breach penalty if
  adopted — confirm per the earlier ops doc's open question on penalties, etc.)
- ~~TDS under Section 194-O~~ — not currently applicable per Anurag's confirmation
  (§4.3); do not build this as an active deduction, keep the field dormant in schema

**Additions:**
- Cancelled-order compensation (when the cancellation wasn't the restaurant's fault)
- Discount cost-share the company/admin committed to bear (e.g. an Opt-In Campaign
  where admin promised to fund 20% of the discount — that 20% is added back here,
  referencing the specific campaign/offer)
- Performance bonuses (e.g. "₹500 bonus on crossing 100 orders this cycle") — reference
  the specific incentive scheme
- Any other manual addition Finance/Admin applies — **require a mandatory reason and
  reference field**, and recommend requiring a second person's approval for manual
  additions since these are discretionary money the company is committing to pay (see
  maker-checker note, §6.3)

### 4.3 Tax handling — confirmed rules (build these, not a placeholder)
- **Service orders (cooked/prepared food)**: GST @ 5%, applied only if the restaurant
  is marked GST-registered (matches the Menu module's `GST_5 (Service)` Tax Type
  option). If the restaurant has no GST registration on file, no GST is charged on
  service orders at all.
- **MRP / Packaged items**: the restaurant enters the applicable GST rate for that
  specific product (varies by classification, e.g. 5/12/18%), and it is already
  included in the MRP price the restaurant sets — nothing is added on top at checkout.
  Matches the Menu module's `MRP / Packaged Item` Tax Type, where Tax Included is
  locked to `Yes`.
- **Who remits it**: Khaalo, as the e-commerce operator, collects the GST portion from
  the customer at checkout and deposits it with the government via **Khaalo's own
  GSTR-3B** — the restaurant never deposits this tax itself. The restaurant separately
  reports these platform-settled supplies in **Table 14 of its own GSTR-1**, for
  reconciliation only.
- **Payout impact**: because this GST is Khaalo's own liability (not the restaurant's
  money), deduct it from what the customer paid before arriving at Net Payable — but
  **frame it to restaurants as "GST is collected and remitted by Khaalo on your
  behalf," not as a deduction from their earnings**, so it doesn't read as an
  unexplained charge (same transparency principle as the earlier ops master-spec, §7.1
  there).
- **New data requirement**: build a separate **GST/ECO Tax Liability Register** —
  tracking total GST collected from customers and owed to the government per filing
  period. This is Khaalo's own statutory obligation, distinct from the restaurant
  payout ledger, and feeds Khaalo's own GSTR-3B filing. Added to §8.
- **TDS under Section 194-O is not currently applicable** — do not build it as an
  active deduction; keep the schema field present but dormant in case this changes with
  future scale or regulation.

Recommend a periodic (e.g. quarterly) review with a CA to keep this current as GST
rules or Khaalo's own registration status evolve — not because these rules are
unconfirmed, but because tax rules change over time.

### 4.4 Open questions on specific line items
- **Delivery Charges Collected** — confirm the model: is this collected by the platform
  on the restaurant's behalf and simply netted out here (as the formula implies), or
  does it belong entirely to the rider-payout side of the system and risk being
  double-counted if also deducted here? Clarify ownership before building.
- **Convenience Fee** — confirm whether this is purely a platform-retained fee charged
  to the customer (in which case it's just being excluded from what the restaurant
  earned, not literally "deducted" from anything they were owed) — wording matters for
  how it's displayed to the restaurant so it doesn't look like an unexplained charge.

---

## 5. Running Ledger (not just discrete weekly snapshots)

Maintain a **per-outlet running balance**, not just isolated per-cycle numbers:
- If a cycle's Net Payable is **negative** (e.g. large refunds/cancellations that week
  exceeded earnings), no transfer happens that week — the negative amount carries
  forward and reduces the *next* cycle's payout. Give this its own status (§6) so it's
  not confused with a missed payment.
- If an order is **refunded/cancelled after its cycle was already marked Paid**, the
  clawback must appear as a deduction in a *later* cycle (this is the reverse direction
  of the same mechanism) — make sure the design handles both directions, not just the
  "already negative" case the brief described.
- This ledger is also what the Restaurant App/Web should show as "current running
  balance," not just "last week's number," so restaurants and AMs always see the true
  state (ties into the reconciliation tooling from the earlier ops doc, §7.4 there).

---

## 6. Marking a Payout as Paid (UTR Entry)

### 6.1 Flow
1. Admin/Finance performs the actual bank transfer **outside this system** (via bank
   portal or payment gateway).
2. Admin then enters into the system: **UTR/reference number**, payment date, amount
   transferred, payment mode (NEFT/RTGS/IMPS/UPI), and optionally a proof/receipt
   attachment.
3. On save, the cycle status flips from `Pending` to `Paid` — this is what unlocks the
   "Paid" state visible in the Restaurant App. Until this step, it must show as
   `Pending / To Be Paid`, exactly as specified.
4. **Amount validation**: flag (don't silently accept) if the entered transferred
   amount doesn't match the calculated Net Payable — require an explicit override reason
   if there's a deliberate partial payment.

### 6.2 Status set (recommended, confirm exact list with Anurag)
`Generated — Pending Payment` → `Paid`, plus recommended additions not explicitly in the
brief but needed for a real system:
- `No Payout Due — Balance Carried Forward` (for negative-cycle weeks, distinct from a
  missed payment)
- `Processing` (if you adopt maker-checker, §6.3 — UTR entered but not yet approved)
- `Failed` (bank transfer bounced/rejected — needs a reattempt workflow)

### 6.3 Maker-checker control (recommended — flag for adoption decision)
Given this module moves real money, recommend a **two-person control**: one Finance
user enters the UTR/payment details (maker), a second, more senior user
approves/confirms before the status flips to `Paid` (checker). This is standard
financial-controls practice and meaningfully reduces fraud/error risk at scale — confirm
with Anurag whether to build this in v1 or treat it as a fast-follow.

### 6.4 Phase 2 — payout automation (recommended, not blocking v1)
Manual UTR entry doesn't scale indefinitely and is error-prone at high restaurant
counts. Recommend evaluating a payout API integration (e.g. a corporate banking API or a
payout-as-a-service provider) to automate the actual transfer and UTR capture instead of
fully manual entry, once volume justifies it. Flag as a roadmap item, not a v1 blocker.

---

## 7. Multi-Sheet Excel Export

Model this on the real multi-tab structure used by existing aggregators, since it's a
proven, restaurant-accountant-friendly format:

| Sheet | Contents |
|---|---|
| **1. Payout Breakup (Summary)** | Cycle date range, total orders (delivered + cancelled/rejected), gross order value, total deductions, total additions, net payout, UTR, settlement date, status |
| **2. Order-Level Detail** | One row per order: order ID, date, order value, discount, tax, commission, convenience fee, delivery charge collected, net order-level settlement, settled/unsettled status |
| **3. Deductions Detail** | One row per deduction: type, amount, reference (e.g. ad campaign ID, penalty reason), notes |
| **4. Additions Detail** | One row per addition: type, amount, reference (e.g. offer/campaign ID, compensated order ID), notes |
| **5. Multi-Cycle Trend** (only when the export spans a date range covering more than one Wednesday cycle) | One row per cycle for the selected range — useful for the From/To date filter use case |

Export must be available to: the **restaurant** (their own data, via Restaurant
App/Web), **Admin/Finance** (any restaurant), and **Account Managers** (their assigned
restaurants only — reuses the scoping from the AM prompt).

**Clarification on the brief's wording:** the original request says "customer can
export payout data" — this document assumes that means the **restaurant/finance user**,
not the platform's end customer (an end customer has no reason to see a restaurant's
payout data). Flag this assumption to Anurag to make sure it's not a typo for something
else.

---

## 8. Data Model

- `PayoutCycle` — id, restaurant_id, outlet_id, cycle_start, cycle_end, generated_at,
  status, gross_order_value, total_deductions, total_additions, net_payable,
  previous_balance_carried, utr_number, payment_date, payment_mode, entered_by
  (maker), approved_by (checker, if adopted), approved_at
- `PayoutOrderLine` — cycle_id, order_id, order_value, discount, tax, commission,
  convenience_fee, delivery_charge_collected, net_settlement, order_status
- `PayoutDeduction` — cycle_id, type, amount, reference_type, reference_id, notes,
  added_by
- `PayoutAddition` — cycle_id, type, amount, reference_type, reference_id, notes,
  added_by, approved_by (recommend requiring approval since these are discretionary
  company-owed amounts)
- `RestaurantPayoutBalance` — restaurant_id/outlet_id, running_balance, last_updated —
  the ledger from §5
- `GstLiabilityRegister` — filing_period, total_gst_collected, total_gst_deposited,
  status (Pending/Deposited), deposited_at — Khaalo's own Section 9(5) remittance
  tracking (§4.3), not part of any individual restaurant's ledger
- Reuse (don't duplicate) `PayoutDispute` and `BankDetailChangeRequest` from the
  earlier Operations/AM master-spec prompt.

---

## 9. Staffing & Process (explicitly requested — who runs this day to day)

| Role | Responsibility |
|---|---|
| **Finance/Payout Executive** (maker) | Performs bank transfers, enters UTR/payment details, monitors weekly cycle generation |
| **Finance Approver/Lead** (checker) | Approves payout entries before `Paid` status is set, if maker-checker is adopted (§6.3) |
| **Finance Reconciliation Analyst** | Investigates payout disputes raised by restaurants/AMs, issues corrections/credit notes (feeds the PayoutDispute workflow) |
| **Compliance/Tax Analyst (or engaged CA)** | Owns correctness of GST 9(5)/TDS 194-O treatment (§4.3), invoice generation accuracy, periodic audit |
| **Account Manager** | First point of contact for a restaurant's payout questions/confusion before escalating to Finance (already scoped in the AM prompt) |
| **Engineering/Ops on-call** | Alerted if the Wednesday 8 AM generation job fails or completes partially — this is a critical scheduled job and needs an owner, not silent failure |

Recommend building a simple RACI (who's Responsible/Accountable/Consulted/Informed) for
each step — generation, transfer, UTR entry, approval, dispute resolution — as a living
process doc alongside the system, not just code. This is cheap to do now and expensive
to reconstruct later once the team has grown.

---

## 10. Restaurant App / Web — Payout Section

- View current pending payout and full history, filterable by date range (mirrors §2).
- **Full transparent breakdown** — every deduction/addition itemized, not just a net
  number — this is a direct extension of the trust/transparency principle established
  in the earlier AM approval-flow prompt (no silent changes to what a restaurant is
  owed).
- Download the multi-sheet Excel export (§7) and the GST-compliant commission invoice
  for the cycle (ties to §7.6 of the earlier ops master-spec).
- **Raise a payout dispute** directly from a specific cycle or line item, routed into
  the Finance queue (reuses the PayoutDispute workflow from the earlier ops prompt).
- AM Workspace shows the same view, scoped to their assigned restaurants, with the
  ability to raise a dispute on the restaurant's behalf.

---

## 11. Validation & Edge Cases

- Generation job is idempotent (§3).
- UTR amount vs calculated Net Payable mismatch requires an explicit override reason
  (§6.1).
- Negative-cycle weeks get their own status, not a blank/confusing `Pending` (§6.2).
- Post-payout refunds/cancellations flow into a *later* cycle's deductions, not into
  the already-`Paid` cycle (§5).
- Zero-order outlets still get a cycle record (§3).

---

## 12. Deliverables Checklist for the Agent

- [ ] DB migrations: `PayoutCycle`, `PayoutOrderLine`, `PayoutDeduction`,
      `PayoutAddition`, `RestaurantPayoutBalance`
- [ ] Wednesday 8 AM generation batch job — async per outlet, idempotent, monitored
- [ ] Calculation engine implementing §4, with tax logic left configurable pending CA
      sign-off
- [ ] Admin Finance: search/select screen (§1), payout screen with filters and history
      (§2), full breakdown view, UTR entry flow (§6), maker-checker if adopted
- [ ] Multi-sheet Excel export (§7), scoped correctly for Admin/Finance, Restaurant, and
      AM users
- [ ] Restaurant App/Web: payout section with transparent breakdown, export, and
      dispute-raising (§10)
- [ ] Running ledger / carry-forward logic (§5), including the post-payout refund
      clawback direction
- [ ] Staffing/RACI documentation alongside the build (§9)
- [ ] Flag all open questions below to Anurag before finalizing that part of the logic

---

## Open Questions to Resolve Before/During Build (do not silently assume)

1. Per-outlet or per-restaurant (aggregated) settlement? (§1) — this is foundational,
   resolve first.
2. Ownership/accounting treatment of Delivery Charges Collected and Convenience Fee —
   confirm they aren't double-counted against a separate rider-payout system. (§4.4)
3. Adopt maker-checker for v1, or fast-follow? (§6.3)
4. Timeline for Phase 2 payout-API automation vs continuing manual UTR entry. (§6.4)
5. Confirm "customer" in the original brief means restaurant/finance user for the
   export feature, not the platform's end customer. (§7)
6. Are SLA-breach penalty deductions actually being adopted (carried over from the
   earlier ops master-spec's open question — this module needs the answer to build the
   deductions line correctly).

**Resolved this turn (no longer open):** GST Section 9(5) mechanics and TDS Section
194-O applicability — see §4.3 for the confirmed rules to build.
