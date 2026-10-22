# 🚨 Wave 3 — Incident Console

One owner per incident. SLA clocks are automatic. Every state change is audited and emitted
as an event (`ops.incident.v1`).

## Lifecycle

`open → ack → resolved`

| Severity | Ack/resolve SLA |
|---|---|
| SEV1 | 5 minutes |
| SEV2 | 15 minutes |
| SEV3 | 60 minutes |

- `incidents:sweep` runs every minute:
  - **at 50% of the SLA window** (still `open`) → `escalation_level=1`, event
    `ops_incident.sla_at_risk`, assigned to a `support_lead` if unowned;
  - **past the SLA** → `sla_breached_at`, `escalation_level=2`, event
    `ops_incident.sla_breached`, team owner if unowned.
- Escalation rows are idempotent: repeated sweeps do not duplicate events.
- Acknowledgement auto-assigns the actor if there is no owner.
- Resolution requires a 10–500 character note.
- Assign is allowed only while open/ack.

## API (`payments`-style admin auth)

| Method | Path | Permission |
|---|---|---|
| GET | `/api/admin/incidents` | `incidents.view` |
| POST | `/api/admin/incidents` | `incidents.manage` |
| POST | `/api/admin/incidents/{id}/ack` | `incidents.manage` |
| POST | `/api/admin/incidents/{id}/resolve` | `incidents.manage` |
| POST | `/api/admin/incidents/{id}/assign` | `incidents.manage` |

Roles: `city_ops` + `support_lead` manage; `whatsapp_ops` and `auditor` view only;
`super_admin` wildcard (still cannot bypass money approvals).

## UI

Admin → **Incident Console** (visible with `incidents.view`): summary cards, create form,
filters, acknowledge/resolve/assign. Command Center shows open incidents, SEV1 count and
SLA breach count, plus a per-city `open_incidents` count.

## Events

`ops.incident.v1`: `ops_incident.opened`, `.acknowledged`, `.resolved`, `.assigned`,
`.sla_at_risk`, `.sla_breached` (payload includes `escalation_level` + `target_role`). Payload includes incident id, city, severity, category, status, owner.
Downstream pages/notifications can consume without tight coupling.

## Test coverage

8 lifecycle checks + 4 role-matrix checks in `tests/wave1_smoke.php`:
create/SLA math, ack, double-ack rejection, resolution note length, double-resolve rejection,
breach sweep idempotency, outbox events, view-vs-manage permissions.
