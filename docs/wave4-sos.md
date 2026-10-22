# 🆘 Wave 4 — Rider SOS + safety partner hook

A rider can raise SOS from the app. The platform responds in three parallel tracks:

1. **Ops** — an automatic SEV1 `safety` incident (Incident Console, 5-minute SLA).
2. **Event bus** — `safety.sos.v1` (`safety.sos_triggered`) on the outbox/Kafka.
3. **Partner** — optional webhook to an insurance/emergency-response provider.

## Rules

- One open SOS per rider; repeated triggers within **5 minutes** return the existing alert
  (`deduplicated: true`) and do **not** create a second incident or partner call.
- Optional `order_id` must belong to that rider.
- Resolution note 10–500 characters.
- Partner failure never blocks the SOS flow (logged, not thrown).
- The rider is never penalised for raising SOS.

## API

| Actor | Method | Path |
|---|---|---|
| Rider | POST | `/api/rider/sos` |
| Ops | GET | `/api/admin/sos` (`sos.view`) |
| Ops | POST | `/api/admin/sos/{id}/ack` (`sos.manage`) |
| Ops | POST | `/api/admin/sos/{id}/resolve` (`sos.manage`) |

Roles: `rider_ops`, `city_ops`, `support_lead` manage; `auditor` views; `super_admin` wildcard.

## Partner contract (disabled by default)

Set `SOS_PARTNER_WEBHOOK` to enable. POST body:

```json
{
  "event": "safety.sos.v1",
  "alert_id": "…",
  "rider_id": "…",
  "order_id": "…",
  "latitude": 22.7196,
  "longitude": 75.8577,
  "triggered_at": "2026-10-21T18:40:00+05:30",
  "severity": "critical"
}
```

Implement `SosPartnerNotifier` for any other provider; keep it non-throwing.

## Ops surfaces

- **Command Center**: Open SOS card (red), plus an automatic SEV1 safety incident.
- **Incident Console**: red SOS panel with rider name/phone, location, order, ack/resolve.

## Test coverage

15 checks in `tests/wave1_smoke.php`: trigger, incident linkage (sev1/safety), partner call,
event emission, 5-minute dedupe, ack/double-ack, resolution note rules, and role matrix.
