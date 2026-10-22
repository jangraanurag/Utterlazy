# OpenCode Agent Prompt — Account Manager Role + Ads/Offers Approval Flow (Khaalo/Welo)

Paste this whole prompt into your OpenCode agent as the task brief. This module ties
into the previously built **Menu**, **Offers**, and **Sponsored Ads** modules — read the
Integration Notes (§8) carefully, since it requires small additions to those.

---

## Role & Context

You are a senior full-stack engineer working on **Khaalo (Welo)**:
- **Customer App** (Flutter)
- **Restaurant App** (Flutter)
- **Admin Panel** (Vue 3 web)
- **Backend**: Laravel 13 microservices

Your task: introduce an **Account Manager (AM)** role — an internal Khaalo team member
assigned by Admin to manage multiple restaurants. When an Account Manager creates an ad
or offer **on behalf of** a restaurant, it must **not go live automatically** — it must
appear in the Restaurant App as a pending item, and only go live after the restaurant
**approves it via an email OTP**.

**Recommended architecture (confirm with Anurag before building):** give Account
Managers a restricted-permission login into the existing **Admin Panel** (Vue 3), scoped
to only their assigned restaurants, rather than building a separate AM app. This reuses
existing auth/infrastructure. If a dedicated AM app is actually wanted, flag it — that's
a materially bigger build.

Build order: **DB schema (AM role, assignments, approval state) → assignment flow
(Admin) → AM-side create flows (reusing existing Ads/Offers forms) → approval queue +
OTP flow (Restaurant App) → audit logging.**

---

## 1. Account Manager — Feature List

1. **My Restaurants Dashboard** — list of all restaurants/outlets currently assigned to
   this AM, with a quick-glance snapshot per restaurant: recent order volume, rating,
   count of active ads/offers, count of items awaiting the restaurant's approval.
2. **Create Offers on behalf of a restaurant** — reuses the exact Offers module forms
   (Promo Codes, BOGO, Opt-In Campaign opt-in) from the earlier prompt, scoped to a
   restaurant the AM is assigned to. Subject to the approval flow in §3.
3. **Create Ads on behalf of a restaurant** — reuses the exact Sponsored Ads module
   forms (PPC, Card Ads) from the earlier prompt, scoped to an assigned restaurant.
   Subject to the approval flow in §3.
4. **Menu assistance** — (recommended capability, **confirm scope**: the brief only
   requires approval for ads/offers, not menu. Decide whether AM-made menu edits go live
   immediately or also need restaurant sign-off — flag this explicitly to Anurag before
   building, don't assume either way.)
5. **Approval status visibility** — AM can see the status of everything they've
   submitted: Pending Restaurant Approval / Approved / Rejected / Expired, with
   timestamps and, if rejected, the restaurant's reason (if collected — see §3).
6. **Restaurant performance view** — read access to order trends, ad/offer performance
   (reusing the Performance tab built in the Ads module), ratings, and complaints for
   assigned restaurants only.
7. **Support ticket handling** — raise or respond to tickets on behalf of an assigned
   restaurant (reuses the ticket system from the Ads module's Help button); escalate to
   Admin when needed.
8. **Activity/communication log** (recommended) — lightweight notes/call-log against a
   restaurant for account-management continuity, e.g. "called owner, discussed Diwali
   campaign opt-in."
9. **Audit trail** — every action an AM takes on a restaurant's behalf must be logged
   (who, what, when, which restaurant) — this is non-negotiable given they're acting on
   someone else's behalf and it touches money (ad spend, discounts).

---

## 2. Restaurant Assignment (Admin-side)

- Admin can create Account Manager accounts (name, email, phone, status active/inactive).
- Admin assigns/reassigns/unassigns restaurants to an Account Manager.
  - Data model: one Account Manager → many Restaurants. Recommend a restaurant has **one
    currently-active AM at a time** (not simultaneous multiple AMs), with a history table
    so reassignments are auditable. Confirm if a restaurant should ever need more than
    one concurrent AM (e.g. by module — one AM for ads, another for offers) — brief
    doesn't mention this, default to single-AM-per-restaurant.
- Admin can view AM workload (restaurant count per AM) and AM activity (via the audit
  log in §1.9) for performance oversight.
- Admin can configure **permission scope** per AM if not all AMs should have full access
  (e.g. some AMs handle only Offers, not Ads) — recommend building this as a permission
  toggle even if all AMs start with full access, to avoid a bigger rework later.

---

## 3. Approval Flow for AM-Created Ads/Offers (core of this request)

### 3.1 State machine addition
Both `Ad` and `Offer` (PromoCode/BOGO/OptInCampaign participation) records need a new
field: `approval_status` with values:
`Not Required` (restaurant created it themselves) → `Pending Restaurant Approval` →
`Approved` or `Rejected` or `Expired`.

An AM-created ad/offer **cannot enter its normal lifecycle** (Scheduled/Active per the
earlier docs) while `approval_status = Pending Restaurant Approval`. It only transitions
to its normal status after `Approved`.

### 3.2 Step-by-step flow
1. AM fills the standard Create Ad / Create Offer form for a restaurant they're assigned
   to. On submit, the record is created with `approval_status = Pending Restaurant
   Approval` and `created_by_type = AccountManager`, `created_by_id = <AM id>`.
2. Restaurant App shows this in a new **Approvals** section/tab: a queue of pending
   items, each showing the full field set from the original Create Ad / Create Offer
   form (read-only review — see open question below on editability), plus who submitted
   it (AM name) and when.
3. Restaurant taps **Approve**.
4. System sends a **6-digit OTP to the restaurant's registered email** (the email on
   file for the restaurant's owner/primary account — see §3.4 for who exactly can
   approve).
5. Restaurant enters the OTP in-app within an expiry window (recommend 10 minutes,
   confirm with Anurag) to confirm the approval.
6. On correct OTP: `approval_status = Approved`, `approved_at`, `approved_via = email_otp`
   recorded; the ad/offer now proceeds into its normal Scheduled/Active lifecycle exactly
   as if the restaurant had created it directly.
7. On **Reject** (instead of Approve): `approval_status = Rejected`; AM is notified;
   record does not go live. Recommend collecting an optional rejection reason so the AM
   knows what to fix — confirm if required or optional.
8. **OTP failure handling**: standard security pattern — limit incorrect attempts (e.g.
   5), lock and require OTP regeneration after that; rate-limit resend requests.
9. **Timeout/expiry handling** (not specified in the brief — confirm): if the restaurant
   never acts before the ad/offer's intended Start Date, recommend auto-expiring the
   pending item (`approval_status = Expired`) and notifying the AM, rather than letting
   it silently miss its start date.

### 3.3 Editability of pending items — open question
Should the restaurant be able to **edit** fields (e.g. reduce the CPC, shorten duration)
before approving, or is it strictly **approve-as-submitted or reject**? Recommend v1 =
approve/reject only, with edits requiring the AM to resubmit — simpler audit trail. Flag
for confirmation.

### 3.4 Who can approve — open question
If a restaurant's Restaurant App account has multiple staff logins, should approval be
restricted to the **owner/primary account** only, or can any staff member with
appropriate permission approve? The OTP goes to "the restaurant's email" — clarify which
email that is if there's more than one contact on file.

### 3.5 Notifications
- Push notification + in-app badge on the Restaurant App when a new item enters their
  Approvals queue.
- Notify the AM (and optionally Admin) when the restaurant approves, rejects, or lets an
  item expire.

---

## 4. Restaurant App Additions

- **Approvals tab/section**: queue of pending Ads/Offers submitted by the AM, each
  opening into the full detail view (reuse the existing Create Ad / Create Offer detail
  layouts) plus Approve / Reject actions and the OTP entry step.
- **History view**: past approved/rejected/expired items, for the restaurant's own
  record-keeping.

---

## 5. Admin Panel Additions

- Account Manager management: create/deactivate AM accounts, assign/reassign
  restaurants (§2).
- AM activity dashboard: audit log, workload, approval/rejection rates across their
  portfolio.
- Ability for Admin to view (not necessarily act on) any restaurant's pending approvals,
  for support/escalation purposes.

---

## 6. Data Model Additions

- `AccountManager` — id, name, email, phone, status. (Prefer extending the existing
  internal staff/user table with a role flag rather than a fully separate user system,
  if one already exists — confirm.)
- `RestaurantAccountManagerAssignment` — restaurant_id, account_manager_id, assigned_by
  (admin id), assigned_at, unassigned_at (nullable) — history-preserving.
- `Ad` / `Offer` tables: add `created_by_type` (Restaurant / AccountManager / Admin),
  `created_by_id`, `approval_status`, `approved_at`, `approved_via`, `rejection_reason`
  (nullable).
- `RestaurantApprovalOTP` — entity_type (Ad/Offer), entity_id, restaurant_id,
  otp_code_hash, expires_at, attempt_count, verified_at.
- `AccountManagerActivityLog` — am_id, restaurant_id, action, entity_type, entity_id,
  timestamp — powers the audit trail in §1.9 and §5.

---

## 7. Security & Authorization Notes

- Enforce restaurant-scoping everywhere: an AM must only be able to view/act on
  restaurants explicitly assigned to them (server-side checks, not just UI hiding).
- OTP delivery and validation should reuse the platform's existing email/OTP
  infrastructure if one already exists (e.g. for login/signup verification) rather than
  building a parallel system — confirm this exists before building new.
- AM actions should never expose restaurant financial/payout details beyond what's
  needed for their ads/offers/menu tasks, unless explicitly required — confirm data
  access boundaries with Anurag.

---

## 8. Integration Notes — Changes Needed in the Earlier Two Prompts

This module requires small additions to the previously specified **Offers Module** and
**Sponsored Ads Module** prompts:
- Add `approval_status`, `created_by_type`, `created_by_id` fields to their data models
  (§6 above already lists these — just confirming they belong on the `Ad` and
  `Offer`/`PromoCode`/`BogoDiscount` tables from those modules, not a new parallel
  table).
- Their status enums (`Scheduled/Active/Paused/Completed` for Ads, similar for Offers)
  should only become reachable once `approval_status = Approved` (or `Not Required` for
  restaurant-created items) — add this as a precondition in their state machines.
- Their "Created By" display (already noted in the Ads module's View Details, §4.3 of
  that prompt) now has a concrete source: `created_by_type`/`created_by_id` from this
  module.

If you want, I can also directly patch the two earlier prompt files with these additions
instead of leaving it as a cross-reference — just say so.

---

## 9. Deliverables Checklist for the Agent

- [ ] DB migrations: `AccountManager` (or role extension), `RestaurantAccountManager
      Assignment`, approval fields on `Ad`/`Offer`, `RestaurantApprovalOTP`,
      `AccountManagerActivityLog`
- [ ] Admin Panel: AM account management + restaurant assignment/reassignment
- [ ] Admin Panel: AM activity/audit dashboard
- [ ] AM access to Create Ads / Create Offers forms, scoped to assigned restaurants
- [ ] Approval state machine wired into existing Ad/Offer lifecycles
- [ ] Email OTP generation, delivery (reuse existing infra if available), verification,
      attempt-limiting, expiry
- [ ] Restaurant App: Approvals tab (queue, detail view, approve/reject, OTP entry, history)
- [ ] Notifications: restaurant on new pending item; AM/Admin on approve/reject/expiry
- [ ] Audit logging for every AM action
- [ ] Flag all open questions below to Anurag before finalizing that part of the logic

---

## Open Questions to Resolve Before/During Build (do not silently assume)

1. Does AM-made **menu** editing also require restaurant approval, or only ads/offers
   as explicitly stated? (§1.4)
2. Can a restaurant have more than one concurrent AM (e.g. split by module), or strictly
   one at a time? (§2)
3. Editability of pending items before approval — approve/reject-as-is only, or can the
   restaurant edit first? (§3.3)
4. Who is authorized to approve — owner account only, or any staff login? Which email
   receives the OTP if multiple contacts exist? (§3.4)
5. OTP expiry window and max attempt count — confirm exact values. (§3.2)
6. Timeout behavior if the restaurant never responds before the item's Start Date —
   auto-expire, or something else? (§3.2)
7. Is rejection reason required or optional? (§3.2)
8. Does Khaalo already have an email/OTP delivery service to reuse, or does this need to
   be built fresh? (§7)
