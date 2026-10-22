# 🗓️ Wave 4 — Ops shifts on org units

Scheduled coverage for support/ops, tied to a **city** and optionally an **org unit** in the
approval hierarchy. Command Center shows live coverage.

## Rules

- `ends_at` must be after `starts_at`.
- Maximum shift length **12 hours**.
- No overlapping `scheduled`/`active` shift for the same user (boundary-touching is allowed).
- Cannot schedule more than 24h in the past.
- Cancel requires a 5–200 character reason.
- Activate only from `scheduled` and only if the shift has not ended.
- `role` is a snapshot (e.g. `support_l1`), independent of later role changes.

## Lifecycle

`scheduled → active → (completed | cancelled)`

Coverage counts shifts that **overlap** the queried day and are not cancelled. On-shift
counts users whose shift covers *now* (`starts_at ≤ now < ends_at`).

## API

| Method | Path | Permission |
|---|---|---|
| GET | `/api/admin/shifts` | `shifts.view` |
| GET | `/api/admin/shifts/coverage` | `shifts.view` |
| POST | `/api/admin/shifts` | `shifts.manage` |
| POST | `/api/admin/shifts/{id}/activate` | `shifts.manage` |
| POST | `/api/admin/shifts/{id}/cancel` | `shifts.manage` |

Roles: `city_ops` + `support_lead` manage; `whatsapp_ops` + `auditor` view only;
`super_admin` wildcard.

## UI

Admin → **Shift Planner**: schedule with agent/role/city/org-unit, day filter, coverage
summary, activate/cancel. Command Center shows **Agents on shift** (global) and
`agents_on_shift` per city.

## Test coverage

13 checks in `tests/wave1_smoke.php`: schedule, overlap rejection, bad window, >12h,
on-shift query, coverage dedupe, activate + double-activate, cancel + reason, cancelled
leaves coverage, and four role-matrix assertions.

## Auto-rotation

`shifts:rotate` builds a deterministic weekly roster from active workforce entries:

```bash
# preview next Monday (default)
php artisan shifts:rotate --city=<uuid> --hours=8 --per-day=1

# apply
php artisan shifts:rotate --city=<uuid> --hours=8 --per-day=1 --apply --actor=ops@utterlazy.com
```

API: `POST /api/admin/shifts/rotate` (`shifts.manage`), `apply` defaults to false.
Admin → Shift Planner has Preview/Apply with a gap list.

Rules:

- Only active workforce entries; optional city filter; `org_unit_id` applied to every entry.
- Existing shifts are respected (no overlap), one shift per agent per day at `per-day=1`.
- Hours clamped to 12; days 1–7; gaps reported per day instead of silently understaffing.
- Apply re-checks overlaps inside a transaction; re-applying is safe (`created=0`, `skipped=N`).
- `--fail-on-gap` exits non-zero for scheduled automation.

## Gaps → staffing incidents

When a rotation is **applied** with gaps, each missing day/city becomes an owned SEV3 incident:

- category `staffing`, severity `sev3`, title `Staffing gap: <city> on <date>`
- auto-assigned to the first `support_lead` admin
- deduped while the incident is `open`/`ack` — re-applying a rotation does not spam ops
- dry-run preview never creates incidents

API response includes `incidents: {created, skipped}`; the CLI prints the same.
Command Center already shows open incidents, SEV1 and SLA breaches.

## Next

- Escalate unstaffed incidents further when SLA breaches (Incident Console already does this).
- Let `shifts:rotate --fail-on-gap` gate automation.
