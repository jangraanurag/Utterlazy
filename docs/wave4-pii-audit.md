# 🔐 Wave 4 — PII access / export audit

Append-only trail for who accessed or exported personal data. This is the evidence layer
for auditor reviews and data-protection obligations.

## What is logged

| Field | Notes |
|---|---|
| actor_user_id + actor_role | who performed the action |
| action | `view` / `export` / `download` |
| resource_type + resource_id | orders, riders, customers, restaurant_report, … |
| filters | the exact query filters used (JSON) |
| row_count | rows included, when known |
| purpose | short operational reason (e.g. `admin_orders_export`) |
| ip_address + user_agent | request context |

**Append-only:** the table has no `updated_at`, and the service exposes no update/delete.
Logging is mandatory for instrumented exports — if the audit insert fails, the export fails
rather than leaking unlogged PII.

## Instrumented (admin)

- `GET /api/admin/reports/orders` → `orders` export (row count)
- `GET /api/admin/reports/revenue` → `revenue` export (row count)
- `GET /api/admin/reports/riders` → `riders` export (row count)
- `GET /api/admin/reporting/export` → `restaurant_report` export

Vendor self-service exports are not admin PII access and are out of scope for this log.

## Auditor access

`GET /api/admin/pii-audit` (`audit.view`) with filters `actor_user_id`, `action`,
`resource_type`, `from`, `to`. Returns 24h summary + paginated rows.

Admin → **PII Audit** page is visible to `auditor` (read-only) and `super_admin`.

Roles: `auditor` has `audit.view`; support/ops/city roles do not. `super_admin` is wildcard
for visibility, but write actions remain governed by the approval engine.

## Verification

```bash
curl -s -H "Authorization: Bearer $ADMIN_TOKEN" \
  "https://catalog.utterlazy.com/api/admin/pii-audit?action=export&from=2026-10-01"
```

Check that every export response is preceded by a row with matching actor, filters, and
`row_count`. Missing rows are a release blocker.

## Retention (manual, audited)

`pii_access_logs` is never purged automatically. Use the dedicated command:

```bash
# 1. dry-run (default) — always start here
php artisan pii:retention --days=180 --actor=auditor@utterlazy.com

# 2. apply only after reviewing the breakdown
php artisan pii:retention --days=180 --actor=auditor@utterlazy.com \
  --apply --confirm=DELETE
```

Rules:

- Minimum window **30 days**.
- `--apply` requires `--confirm=DELETE` (case-sensitive); otherwise refused.
- Deletes in chunks of 1000; every run (dry-run or applied) is recorded in
  `pii_retention_runs` with cutoff, breakdown, deleted count and actor.
- Only `pii_access_logs` is touched. SOS, incidents, orders and audit trails are never purged
  by this command.
- Auditor UI: PII Audit → **Retention runs**.
