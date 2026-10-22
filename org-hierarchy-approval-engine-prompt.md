# OpenCode Agent Prompt — Organizational Hierarchy & Approval Workflow Engine (Khaalo/Welo)

Paste this whole prompt into your OpenCode agent as the task brief. This is a
**foundational platform capability**, not a single feature screen — it's the engine
that Finance/Payout, Account Management, Rider Ops, and Support will all plug into as
Khaalo scales. Read §1's scaling correction before building anything — it changes the
literal example in the brief in one important way.

---

## Role & Context

You are a senior full-stack engineer working on **Khaalo (Welo)**:
- **Admin Panel** (Vue 3 web)
- **Backend**: Laravel 13 microservices

Your task: build a **generic, configurable Organizational Hierarchy + Approval Workflow
Engine** — not a Finance-only feature. The brief's example (Chief Accounts Officer →
State Account Officers → District Officers → Regional Officers where districts are
large) is the *reference case*, but the engine must be reusable by any department
(Finance/Accounts, Account Management, Rider Ops, Support), each defining its own tree
depth and roles on the same underlying system.

Build order: **Generic org-unit/hierarchy schema (§3) → configurable Delegation of
Authority / threshold matrix (§4) → approval workflow engine with audit trail (§5) →
Admin Panel: org chart builder, threshold config, approval queue, workflow
visualization (§8) → apply to Finance first as the reference implementation, then AM/
Rider Ops/Support (§6).**

---

## 1. Critical Scaling Correction — read this before building the literal example

The brief's example has every payment travel through the full chain up to the CAO, who
"will make payment or ask queries." **At the scale you've described — lakhs of
restaurants, crores of customers — literally routing every single payment to one person
at the top does not scale, no matter how deep the hierarchy under them is.** This is
also not how real large organizations actually run their approval chains, even ones
with a CAO at the top.

**The real pattern (confirmed by how Delegation of Authority matrices work in practice
at scale):** each level in the hierarchy has an **approval threshold** — an amount (or
risk profile) up to which that level can approve **on their own, without escalating
further**. A District Account Officer might approve routine payments up to ₹X
independently; only amounts above their threshold, or items flagged as exceptions
(unusual amount, first-time recipient, a flagged dispute, anything that looks
anomalous) escalate to the State level, and so on. The CAO should only ever see a small
number of large or exceptional items — not the lakhs of routine ones. **This is what
actually lets a hierarchy scale**: routine work gets resolved close to where it
originates, and escalation is the exception, not the default path.

Build the engine this way — with a configurable threshold per role (§4) — rather than
the literal "everything reaches the CAO" flow. Confirm this correction with Anurag
before building; it's a meaningful change to how the example was described, made for a
concrete scaling reason, not a stylistic preference.

---

## 2. Design Principle — one generic engine, many departments

Don't build a Finance-specific approval chain. Build:
- A generic **Org Unit** hierarchy (variable depth — see §3)
- A generic **Role/Designation** concept with an approval threshold and escalation
  target, resolved from the org tree
- A generic **Approvable Entity** interface any module can implement (a Payment, a
  Payout exception, an Invoice correction, a KYC verification, an Account Manager
  reassignment, etc.)
- A generic **Approval Workflow Engine** (state machine + audit trail) that operates on
  any Approvable Entity, routed through whichever department's org tree is relevant

This means the Finance/Accounts hierarchy in the brief is the **first instantiation**
of this system, not a separate one-off build. Design accordingly from day one — 
retrofitting genericness after Finance is hardcoded is expensive.

---

## 3. Org Unit Hierarchy — variable depth, geography-based

The brief explicitly says regional officers exist "if districts are big, else not" —
meaning **the tree depth varies by branch**, not a fixed number of levels for the whole
country. Build this as a **recursive/self-referencing structure**, not fixed columns:

`OrgUnit` — id, department, unit_type (e.g. National / State / District / Region-within-
District), parent_org_unit_id (nullable — null at the top), geography_scope (which
states/districts/pincodes/outlets fall under this unit), name.

A given state's branch might go `National → State → District → CAO's chain` directly;
another state's branch might go `National → State → District → Region → CAO's chain` —
both must be valid under the same schema, decided per-branch by whoever configures the
org chart (Admin), not hardcoded in code.

---

## 4. Delegation of Authority (DOA) / Approval Threshold Matrix

This is the actual rulebook — and it must be **live configuration data that drives the
workflow engine**, not a static document nobody enforces (this is the single most
common failure mode of DOA matrices in real organizations — treat the matrix as
executable rules, not policy paperwork).

`ApprovalThresholdConfig` — department, org_unit_type (or specific role), entity_type
(Payment/Payout Exception/Invoice Correction/KYC/etc.), max_amount (nullable for
non-financial entity types — define an equivalent "complexity/risk" gate instead), SLA
hours to act.

**Design guidance from how real DOA matrices are built:** thresholds typically step up
roughly an order of magnitude per level (e.g. District ₹X → State ₹10X → Region ₹100X
→ CAO unlimited/above), reflecting what each role can realistically evaluate. Use this
as a starting shape, then let Anurag set Khaalo's actual numbers — don't invent the
real ₹ figures yourself.

Make this matrix **editable by Admin through the UI** (§8), not a code deploy, since
thresholds will need tuning as Khaalo's scale and risk tolerance change.

---

## 5. Approval Workflow Engine

### 5.1 States
`Originated` → `Under Review at <Org Unit>` → (`Approved` | `Escalated to <higher Org
Unit>` | `Sent Back for Clarification to <any lower Org Unit>` | `Rejected`)

### 5.2 Flow
1. An Approvable Entity (e.g. a payment) originates at the **lowest relevant org unit**
   for its geography (e.g. the district covering the restaurant/rider in question).
2. That unit reviews, analyzes, and can update supporting details.
3. If the amount/risk is within that unit's threshold (§4): **they can approve
   directly** — this is the common case and should be the fast path.
4. If above threshold, or flagged as an exception: **escalate** to the next unit up the
   tree (auto-resolved from the org hierarchy's parent link — the reviewer shouldn't
   have to manually pick who's next).
5. **At any level**, the current reviewer can **send it back for clarification** to any
   specific lower level in the chain (not necessarily just one level down) — with a
   required comment explaining what's needed. This pauses upward progress until the
   flagged level responds and resubmits.
6. At the top of a given branch (e.g. CAO for Finance), the final authority either
   **approves** (triggers the actual downstream action — e.g. the payment transfer) or
   **raises a query**, sending it back to any level, exactly as described in the brief.
7. **Full audit trail**: every step (who, which org unit, what action, what comment,
   when) is recorded — non-negotiable at this scale, both for internal accountability
   and because Finance-type approvals are exactly the kind of record regulators and
   auditors will eventually want to see.

### 5.3 SLA & escalation-on-timeout
Each level gets an SLA to act (from §4's config). If a unit doesn't act within its SLA,
auto-escalate upward and flag it — don't let items silently sit in a queue indefinitely,
which is exactly the failure mode that makes large hierarchies feel slow and
untrustworthy to the people waiting on them.

### 5.4 Vacancy / absence handling
If a role in the chain is vacant or the assigned person is unavailable (leave, etc.),
the item must not get stuck. Recommend a designated backup/deputy per role, or
auto-escalation to the next level up if no backup is configured — confirm the exact
policy with Anurag, but build *something* here; an org chart with no failover plan will
break the first time someone goes on leave.

---

## 6. Applying This to Khaalo's Actual Departments

| Department | Reference hierarchy (build Finance first, as the given example) |
|---|---|
| **Finance/Accounts** | CAO → (Regional Account Officer, only for large districts) → District Account Officer → State Account Officer — exactly the brief's example. Approvable entities: Payout confirmations/exceptions, Invoice corrections/credit-notes, Recovery approvals — all from the earlier Finance prompts. |
| **Account Management** | Extend the earlier AM prompt: a Head of Account Management → Regional/State AM Lead → District-level AM assignment, using the same org-unit schema. Restaurant-to-AM assignment (already built) becomes geography-routed through this hierarchy instead of a flat Admin-assigns-anyone model. |
| **Rider Ops / Fleet** | Extend the earlier Rider App prompt's "Rider Ops / Fleet team": National Fleet Head → State Fleet Manager → District Fleet Coordinator, handling KYC verification queues and daily payout maker-checker at the appropriate geographic level instead of one flat Admin team. |
| **Support/CRM** | From the earlier Ops master-spec: City/Region-level Support Team Leads reporting up to a Head of Support, using the same engine for ticket-escalation thresholds (e.g. refund amount above a Support Agent's authority escalates through this same mechanism, not a separate one-off rule). |

Building Finance first as the reference implementation, then porting the same engine to
the other three, is far cheaper than building four separate hierarchy systems — this is
the entire point of §2's genericness requirement.

---

## 7. Geography Data Requirement (a real prerequisite, not optional)

For the engine to route an item to the correct org unit automatically, every
restaurant/outlet and rider record needs a **standardized District field** (not free-
text city/locality alone) — check whether this already exists cleanly in the Menu
module's outlet address data (built earlier) or needs to be added/backfilled. Indian
addresses commonly separate State, District, and City/locality — confirm all three are
captured as structured fields, since District is the unit this whole hierarchy hinges
on.

---

## 8. Admin Panel Requirements

- **Org Chart builder**: create/edit org units at any depth, assign people to roles,
  define each unit's geography scope, all without a code deploy
- **DOA/Threshold matrix configuration UI** (§4): editable by a suitably senior Admin
  role, with its own change-audit trail (changing who can approve how much is itself a
  sensitive action)
- **Approval Queue** (per logged-in user): items currently awaiting their action,
  filterable/sortable, showing the entity, amount/risk, how long it's been waiting, and
  one-click Approve / Escalate / Send Back actions
- **Workflow visualization**: for any item, show its full journey so far (which units
  touched it, what they did, comments) — both for the people currently handling it and
  for later audit
- **SLA breach dashboard**: surfaces items at risk of or past their SLA, for oversight

---

## 9. Data Model

- `OrgUnit` — id, department, unit_type, parent_org_unit_id, geography_scope, name
- `OrgRole` — id, org_unit_id, title, assigned_user_id, backup_user_id
- `ApprovalThresholdConfig` — department, org_unit_type/role, entity_type, max_amount
  (nullable), sla_hours
- `ApprovalWorkflowInstance` — id, entity_type, entity_id, origin_org_unit_id,
  current_org_unit_id, status, created_at
- `ApprovalWorkflowStep` — workflow_instance_id, org_unit_id, actor_user_id, action
  (Approved/Escalated/Sent Back/Rejected), comment, timestamp — the audit trail from
  §5.2
- Any module implementing an Approvable Entity (Payout, Invoice, KYC, etc.) links to
  `ApprovalWorkflowInstance` rather than building its own parallel approval logic

---

## 10. Validation & Edge Cases

- Variable tree depth per branch must be supported without special-casing in code
  (§3) — test with at least one deep branch (with Regional layer) and one shallow
  branch (without it) side by side
- Threshold changes shouldn't retroactively alter items already mid-flow — apply new
  thresholds to newly originated items only, unless Anurag wants retroactive
  re-evaluation (confirm)
- Vacancy/backup handling actually tested — simulate a vacant role and confirm items
  don't stall (§5.4)
- Send-back-for-clarification must support targeting *any* lower level, not just the
  immediate one below the current reviewer (§5.2)

---

## 11. Deliverables Checklist for the Agent

- [x] Generic `OrgUnit`/`OrgRole` schema supporting variable-depth, geography-scoped
      hierarchies (§3)
- [x] Configurable `ApprovalThresholdConfig` (DOA matrix), editable via Admin UI, not
      hardcoded (§4)
- [x] Approval Workflow Engine: states, escalation, send-back-to-any-level, SLA
      auto-escalation, full audit trail (§5)
- [x] Vacancy/backup handling (§5.4)
- [x] Reference implementation for Finance/Accounts, matching the brief's example
      structure but with threshold-based (not always-to-CAO) routing (§1, §6)
- [x] District (and State) as standardized, structured fields on outlet/rider address
      data — confirm/backfill as needed (§7)
- [x] Admin Panel: Org Chart builder, threshold config, Approval Queue, workflow
      visualization, SLA dashboard (§8)
- [x] Port the same engine to Account Management, Rider Ops, and Support once Finance
      is validated (§6) — don't rebuild per department
- [x] Flag all open questions below to Anurag before finalizing that part of the logic

---

## Open Questions to Resolve Before/During Build (do not silently assume)

1. Confirm the threshold-based escalation model (§1) replaces the literal
   "everything-reaches-the-CAO" flow described in the brief — this is the single
   biggest design decision in this document and needs explicit sign-off.
2. Actual ₹ threshold values per role/level, per entity type — this document gives the
   shape (order-of-magnitude step-ups), not Khaalo's real numbers.
3. Vacancy/backup policy — designated deputy vs auto-escalate-on-absence, or both.
4. Which departments get this hierarchy treatment in which order after Finance —
   confirm the §6 sequencing (AM, Rider Ops, Support) matches Anurag's priorities.
5. Whether threshold/entity-type rules should ever be retroactive to in-flight items,
   or only apply going forward.
6. Confirm District/State are already structured, standardized fields on
   outlet/rider records, or need a data-model addition/backfill (§7).
