# UtterLazy — Too Lazy? Just Order.

> **Tier 2 & Tier 3 India ka apna food delivery platform.**
> Aggregator model — restaurants register hote hain, hum unhe orders + reach dete hain.
> **Flutter-first. Mobile is primary. Web is support.**

---

## Quick Start

### Prerequisites
- Docker & Docker Compose
- PHP 8.3 (for local dev without Docker)
- Composer
- Flutter 3.24+ (for mobile apps)
- Node.js 22+ (for web apps)

### Run with Docker

```bash
# Copy env files
cp api-gateway/.env.example api-gateway/.env
cp catalog-service/.env.example catalog-service/.env

# Start all services
docker compose up -d

# Run migrations
docker compose exec catalog-service php artisan migrate --seed
docker compose exec api-gateway php artisan migrate --seed

# Check health
curl http://localhost:8001/api/health
curl http://localhost:8000/api/health
```

### Run without Docker (Local Dev)

```bash
# ─── Catalog Service ───
cd catalog-service
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve --port=8001

# ─── API Gateway ───
cd api-gateway
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve --port=8000
```

### Flutter Apps

```bash
cd mobile
flutter pub get
melos bootstrap

# Run customer app
cd apps/customer_app
flutter run

# Run restaurant app
cd apps/restaurant_app
flutter run

# Run rider app
cd apps/rider_app
flutter run
```

### Web Apps

```bash
# Admin Panel (Vue 3)
cd web/admin-web
npm install && npm run dev

# Customer Web (Vue 3)
cd web/customer-web
npm install && npm run dev

# Vendor Web (Vue 3)
cd web/vendor-web
npm install && npm run dev
```

---

## Architecture

```
┌─────────────────────────────────────────┐
│            DOCKER COMPOSE               │
│  MySQL │ Redis │ Reverb │ Worker │ Cron │
└──────────────────┬──────────────────────┘
                   │
    ┌──────────────┴──────────────┐
    │                             │
┌───▼──────────┐          ┌──────▼───────┐
│ API GATEWAY  │─────────▶│   CATALOG    │
│ Laravel 13   │  proxy   │   SERVICE    │
│ Port 8000    │          │ Laravel 13   │
│ Auth + Proxy │          │ Port 8001    │
│ + Filament   │          │ All APIs     │
└──────────────┘          └──────────────┘
    │
    ├── 🔷 Flutter Apps (PRIMARY)
    │   Customer App (33 screens)
    │   Restaurant App (22 screens)
    │   Rider App (10 screens)
    │
    └── 🌐 Web Apps (Secondary)
        Admin Panel (Vue 3, 30+ pages)
        Customer Web (Vue 3, 26 pages)
        Vendor Web (Vue 3, 12 pages)
```

---

## Services

| Service | Port | Tech | Purpose |
|---------|------|------|---------|
| `api-gateway` | 8000 | Laravel 13 | Auth, FCM, proxy to catalog (`/api` + `/images` assets) |
| `catalog-service` | 8001 | Laravel 13 | All business logic + 150+ API endpoints + approval engine |
| `whatsapp-service` | 8002 | Laravel 13 | Meta webhook, conversation FSM, paid WhatsApp recommendations |
| `outbox-publisher` | — | Laravel worker | Outbox → Kafka (at-least-once, DLQ) |
| `whatsapp-consumer` | — | Laravel worker | `order.events.v1` → utility templates |
| `kafka` | 9092 | KRaft | Event backbone (3 brokers in prod) |
| `order-command` | 8003 | Java 17 + Spring Boot | Wave 2 shadow order owner (compose profile `wave2`, not in the live path) |
| `queue-worker` | — | Laravel | Async job processing (Redis queue — drains Redis, not MySQL) |
| `scheduler` | — | Laravel | Scheduled tasks (schedule:run loop, sole runner) |
| `reverb` | 8081→8080 | Laravel Reverb | WebSocket real-time updates |
| `mysql` | 3306 | MySQL 8.0 | Primary database |
| `redis` | 6379 | Redis 7 | Cache, queues, sessions |

---

## Key Features

- **Customer:** Search, cart, checkout, live order tracking, group orders, scheduled orders, reviews, favorites, wallet, gift cards, referrals, mini games
- **Restaurant:** Dashboard, order management, menu CRUD, earnings, analytics, inventory, combos, staff, sponsored ads, GST reports, payouts, invoices
- **Rider:** GPS tracking, auto-assignment, trip management, OTP verification, earnings, payouts, KYC
- **Admin:** Full dashboard, order rescue, fraud detection, rider/vendor/customer management, accounting, ranking config, push campaigns, **Org Hierarchy + Approval Workflow Engine** (finance payouts/invoices/recoveries/GST, rider payouts/KYC, AM ads/offers/assignments, support refunds — with thresholds, SLA escalation, audit trail)

Full feature audit: [`build_status.md`](build_status.md)

Wave 1 design + operations: [`docs/architecture-v2.md`](docs/architecture-v2.md),
[`docs/hld.md`](docs/hld.md), [`docs/lld.md`](docs/lld.md),
[`docs/deploy-runbook.md`](docs/deploy-runbook.md),
[`docs/wave2-order-command.md`](docs/wave2-order-command.md),
[`docs/pricing-parity.md`](docs/pricing-parity.md),
[`docs/wave3-ranker.md`](docs/wave3-ranker.md),
[`docs/wave4-pii-audit.md`](docs/wave4-pii-audit.md),
[`docs/wave4-shifts.md`](docs/wave4-shifts.md),
[`docs/wave4-sos.md`](docs/wave4-sos.md),
[`docs/wave3-ondc.md`](docs/wave3-ondc.md),
[`docs/wave3-ranker-training.md`](docs/wave3-ranker-training.md),
[`docs/wave3-ranker-serving.md`](docs/wave3-ranker-serving.md),
[`docs/certification.md`](docs/certification.md),
[`docs/scale-path.md`](docs/scale-path.md),
[`docs/wave2-cutover-readiness.md`](docs/wave2-cutover-readiness.md),
[`docs/wave2-projection.md`](docs/wave2-projection.md),
[`docs/wave2-opensearch.md`](docs/wave2-opensearch.md),
[`docs/build-checklist.md`](docs/build-checklist.md).

---

## External Integrations

| Service | Purpose |
|---------|---------|
| Razorpay | Payment gateway |
| Firebase (FCM) | Push notifications |
| Ola Maps | Maps + geocoding |
| EnableX | Voice/video calls |
| Google Gemini | AI recommendations + search |
| Meta WhatsApp Cloud API | WhatsApp ordering + utility templates |
| Fast2SMS | SMS OTP (backend ready, not active) |

---

## Project Structure

```
├── api-gateway/          # Laravel — Auth, FCM, proxy (/api + /images)
├── catalog-service/      # Laravel — All business APIs + approval engine
├── whatsapp-service/     # Laravel — WhatsApp channel (webhook, FSM, paid reco)
├── services/
│   ├── order-command-core/  # Java — framework-free domain core + Spring adapter (shadow)
│   └── ranker/              # Python — organic ranking (never sponsored)
├── docs/                 # architecture-v2, hld, lld, runbook, checklists
├── scripts/              # wave1_deploy, wave1_verify, order_core_test
├── mobile/               # Flutter monorepo (Melos)
│   ├── apps/
│   │   ├── customer_app/
│   │   ├── restaurant_app/
│   │   └── rider_app/
│   └── packages/
│       ├── api/           # Dio client + models + repos
│       ├── core/          # State (Riverpod) + services
│       └── ui/            # Shared components + theme
├── web/
│   ├── admin-web/         # Vue 3 + TS Admin Panel
│   ├── customer-web/      # Vue 3 Customer Web
│   └── vendor-web/        # Vue 3 Vendor Web
├── docker-compose.yml
└── build_status.md        # Feature audit + roadmap
```

---

## License

Proprietary. All rights reserved.
