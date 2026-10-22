# 🏗️ UtterLazy (Khaalo / Welo) — Architecture

## Business Model
```
┌─────────────────────────────────────────────────────┐
│              PURE AGGREGATOR MODEL                  │
│  We don't run restaurants. We register partners.   │
│  Like Swiggy/Zomato — platform + tools only.       │
└─────────────────────────────────────────────────────┘

CUSTOMER ──order──▶ PLATFORM (Khaalo/Welo) ──order──▶ RESTAURANT (Partner)
                          │                                      │
                          │  Tools provided: Menu, KDS, POS,     │
                          │  Inventory (opt-in), Analytics etc.  │
                          │                                      │
                          └────assign──▶ RIDER (Delivery Partner)
```

## System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    NGINX REVERSE PROXY                      │
│  utterlazy.com → Customer Web                               │
│  admin.utterlazy.com → Admin Panel                          │
│  vendor.utterlazy.com → Vendor Web                          │
│  api.utterlazy.com → API Gateway (:8000)                    │
│  catalog.utterlazy.com → Catalog API (:8001)                │
│  ws.utterlazy.com → WebSocket (Reverb)                      │
└──────────────────────┬──────────────────────────────────────┘
                       │
         ┌─────────────┼─────────────────────────┐
         │             │                         │
    ┌────▼────┐   ┌────▼────┐   ┌─────────▼──────┐
    │  API    │   │ CATALOG │   │   REVERB WS    │
    │ GATEWAY │◄──│ SERVICE │   │ :8081→8080     │
    │ :8000   │   │ :8001   │   │  (separate     │
    │ Laravel │   │ Laravel │   │   container)   │
    │ Auth +  │   │ All API │   └────────────────┘
    │Proxy +  │   │ Logic   │
    │ FCM     │   │ 150+    │
    └────┬────┘   │ Endpts  │
         │        └────┬────┘
         │             │
         └──────┬──────┘
                │
        ┌───────▼───────┐        ┌──────────────────────┐
        │    MYSQL 8    │        │     REDIS 7          │
        │ food_gateway  │        │ Cache + QUEUE        │
        │ food_catalog  │        │ (queue:default)      │
        └───────────────┘        └──────────┬───────────┘
                                            │
                               ┌────────────┴────────────┐
                               │ QUEUE WORKER (separate  │
                               │ container) drains Redis │
                               └─────────────────────────┘

  Separate containers (all --restart unless-stopped):
  • utterlazy-catalog-service   :8001  (app code)
  • utterlazy-api-gateway      :8000  (proxy /api + /images)
  • utterlazy-queue-worker            (queue:work --sleep=3 --tries=3, Redis)
  • utterlazy-scheduler               (schedule:run loop, sole runner)
  • utterlazy-reverb           :8081  (websockets, in-network utterlazy-reverb:8080)
  • utterlazy-mysql / utterlazy-redis / utterlazy-nginx
```

## Frontend Architecture

```
                      FLUTTER (PRIMARY)
        ┌─────────────────────────────────┐
        │  packages/api  │ packages/core  │
        │  (Dio + Models)│ (State + Svc) │
        └─────────────────────────────────┘
        │           │           │
   ┌────▼────┐ ┌────▼────┐ ┌───▼────┐
   │CUSTOMER │ │RESTAURNT│ │ RIDER  │
   │ 33 scrns│ │ 22 scrns│ │ 18 scrns│
   └─────────┘ └─────────┘ └─────────┘

                      WEB (SECONDARY)
        ┌──────────┐ ┌──────────┐ ┌──────────┐
        │ CUSTOMER │ │  ADMIN   │ │  VENDOR  │
        │  Vue 3   │ │  Vue 3   │ │  Vue 3   │
        │  Tailwind│ │  + TS    │ │  Tailwind│
        └──────────┘ └──────────┘ └──────────┘
```

## Approval Workflow Engine (Org Hierarchy)

```
Generic engine — ONE codebase, ported to 4 departments (no per-dept rebuild):

  OrgUnit (variable-depth, geography-scoped)      OrgRole (assigned + backup user)
        │                                                 │
        ▼                                                 ▼
  ApprovalThresholdConfig (DOA matrix: dept × unit_type × entity_type × max_amount)
        │
        ▼
  ApprovalWorkflowEngine (states: under_review → approved / rejected / sent_back)
        │   originate at deepest geo-matching unit
        │   approve (fast path, within pinned threshold)
        │   escalate (parent), send-back to ANY lower level, resubmit
        │   SLA auto-escalation (approvals:check-sla, 15 min), vacancy/backup
        │   §10: threshold pinned at landing — config changes never affect in-flight items
        ▼
  Finalizers per entity (bridge pattern):
    vendor_payout, invoice, recovery, gst_filing  → Finance
    rider_payout, kyc_verification               → Rider Ops
    sponsored_listing, offer, restaurant_assignment → Account Management
    complaint_refund                             → Support
        │
        ▼
  Audit trail: approval_workflow_steps (actor/org/comment/timestamp) + admin_audit_logs
```

## Data Flow

```
Order Flow:
  Customer App → API Gateway → Catalog Service → MySQL
       ↓                                              ↓
  Order Created → Rider Assignment Service → Redis Queue
       ↓                                              ↓
  Restaurant KDS (WebSocket) ← Reverb ← Order Accepted
       ↓
  Rider app notified → Pickup → Deliver
       ↓
  Payment: Razorpay / COD
```

## Tech Stack

| Layer | Technology |
|-------|-----------|
| **Backend** | PHP 8.3, Laravel 13, Sanctum |
| **Mobile** | Flutter 3.24+, Dart 3.11+, Riverpod |
| **Customer Web** | Vue 3.5, Vite 8, Tailwind CSS 4 |
| **Admin Web** | Vue 3.5, Vite 8, TypeScript |
| **Vendor Web** | Vue 3.5 (migrated from React) |
| **Database** | MySQL 8.0 |
| **Cache** | Redis 7 |
| **WebSocket** | Laravel Reverb |
| **AI** | Google Gemini + DeepSeek |
| **Maps** | Ola Maps API |
| **Payments** | Razorpay |
| **CI/CD** | GitHub Actions |
| **Container** | Docker + Docker Compose |

## Directory Structure

```
├── api-gateway/          # Laravel — Auth, FCM, proxy (/api + /images assets)
├── catalog-service/      # Laravel — All business APIs (~150 endpoints) + approval engine
├── mobile/               # Flutter monorepo (Melos)
│   ├── apps/
│   │   ├── customer_app/    # 33 screens
│   │   ├── restaurant_app/  # 22 screens
│   │   └── rider_app/       # 15 screens
│   └── packages/
│       ├── api/             # Dio client + models + repositories
│       ├── core/            # State (Riverpod) + services
│       └── ui/              # Shared components + theme
├── web/
│   ├── admin-web/           # Vue 3 + TS (30+ pages incl. approval engine UI)
│   ├── customer-web/        # Vue 3 + Tailwind (26 pages)
│   └── vendor-web/          # Vue 3 (11 pages)
├── docker-compose.yml
├── docker-compose.prod.yml
├── nginx.conf
├── deploy.sh
├── e2e/                     # Playwright tests
└── build_status.md
```

## Approval Engine Key Files (catalog-service)
| File | Purpose |
|------|---------|
| `app/Services/ApprovalWorkflowEngine.php` | Core engine + finalizer registry + §10 threshold pinning |
| `app/Services/*ApprovalBridge.php` | Per-department originate + finalizers |
| `app/Http/Controllers/Api/OrgApprovalController.php` | Queue, SLA, thresholds, per-dept endpoints |
| `app/Models/{OrgUnit,OrgRole,ApprovalWorkflowInstance,ApprovalWorkflowStep,ApprovalThresholdConfig}.php` | Org + workflow models |
| `tests/engine_smoke.php` | 19 suites / 93 PASS (needs `/tmp/opencode/schema.sql` sqlite stub) |
| `api-gateway/routes/web.php` | `/images/*` asset proxy to catalog (no /api prefix) |
| `config/broadcasting.php` | Reverb client config (secret, options.host=`utterlazy-reverb`, app_id=`utterlazy`) |
