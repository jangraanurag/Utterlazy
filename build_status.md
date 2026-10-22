# 🍽️ UtterLazy — Build Status & Feature Audit (Final)

> **Too Lazy? Just Order. — Food Delivery Aggregator for Tier 2 & Tier 3 Indian Cities**
> *Pure Aggregator Model — Flutter-first, Web secondary*
> **Last Updated:** 3 August 2026 (Session: Org Hierarchy + Approval Engine, Notification Infra Fix, Mobile Bug Fixes)

---

## 📊 Project Status

| Area | Completion |
|------|-----------|
| **Backend (Laravel)** | 🟢 ~99% |
| **Customer Web (Vue)** | 🟢 ~95% |
| **Admin Web (Vue + TS)** | 🟢 ~98% |
| **Vendor Web (Vue)** | 🟢 ~90% |
| **Customer Flutter App** | 🟢 ~95% |
| **Restaurant Flutter App** | 🟢 ~95% |
| **Rider Flutter App** | 🟢 ~99% |
| **Infrastructure** | 🟢 ~97% (Docker, nginx, Reverb, Redis queue, crash auto-restart) |
| **Documentation** | 🟡 ~80% |

---

## ✅ BUILT FEATURES (~65+ Total)

### Customer Platform
- [x] Auth (register/login/OTP/forgot password/Sanctum tokens)
- [x] Restaurant browsing, search (text + voice + advanced filters)
- [x] Menu viewing with dietary filters, **prep type labels**, serving info
- [x] Cart management (add/update/remove/clear) **with variant & add-on selection**
- [x] Checkout + Razorpay payments + COD **with real promo code input + discount via DiscountCalculationService**
- [x] Order history, tracking (live with ETA, rider location, timeline) **with rawItems detail**
- [x] Repeat order / quick reorder (one-tap)
- [x] Scheduled orders, Group orders
- [x] Favorites, Reviews, Ratings, Complaints
- [x] Wallet, Gift cards, Referral program v2
- [x] Meal Plans / Tiffin subscriptions (daily/weekly)
- [x] Sachet Pricing / Delivery Passes (₹10-₹200)
- [x] Spin the Wheel / Lucky Draw (daily free spin)
- [x] Price Drop Alerts
- [x] Calorie / Nutrition Info per dish
- [x] Catering / Bulk Orders
- [x] Donate a Meal
- [x] Self Pickup Mode
- [x] QR Code Table Ordering (dine-in)
- [x] Social Feed + Likes + Leaderboards
- [x] Food Image Gallery (customer-uploaded photos)
- [x] Food Blog / Magazine, Recipe Integration
- [x] Multi-language (backend ready, UI deferred)
- [x] Dark mode (web + Flutter)
- [x] Voice search (Hindi/English/Hinglish via Gemini NLP)
- [x] Voice assistants (Google Assistant/Dialogflow, Alexa, Gemini, Meta)
- [x] Live Kitchen Streams
- [x] AR Menu Preview
- [x] Crypto Payments (BTC/ETH/USDT)
- [x] NFT Collectibles
- [x] Drone Delivery tracking
- [x] App Shortcuts (long-press quick actions)
- [x] Home Screen Widget (live order status)
- [x] PWA / Offline support (service worker)
- [x] Membership (built, disabled, admin-toggleable)
- [x] **Sponsored ad placements with impression/click logging**

### Restaurant/Vendor Platform
- [x] Profile, compliance (FSSAI/GST/PAN), bank details
- [x] Menu management (CRUD with variants, add-ons, modifiers, GST, **name_local, reel_url, global_category_id**)
- [x] Combo offers, Promotional offers
- [x] Order management (accept/reject/status updates/cancel)
- [x] Incoming order real-time screen, Kitchen Display System (KDS)
- [x] Earnings dashboard, GST reports, Invoices
- [x] Accounting Excel exports
- [x] Staff management + Shift management (clock-in/out)
- [x] Inventory management (simple on/off toggle) — ESSENTIAL
- [x] Advanced inventory (raw materials, recipes, auto-deduction) — OPTIONAL, opt-in
- [x] Suppliers management (vegetables, meat, dairy, bakery)
- [x] POS Integration (Petpooja, Posist, Limetray, Inresto, DotPe)
- [x] AI Menu Photo → Digital Menu (Gemini Vision)
- [x] Smart Prep Time Prediction (ML-based)
- [x] Print KOT / Thermal Printer
- [x] Sponsored Listings / Ads **with GSP auction, budget pacing, CPC recommendations, performance tracking**
- [x] Analytics dashboard
- [x] Customer Insights dashboard
- [x] Competitor Price Benchmarking
- [x] Video Menu / Reel Creator
- [x] Dark Kitchen / Virtual Brands
- [x] Multi-city expansion toolkit (clone restaurant configs)
- [x] **Payout cycles view (balance, generated/paid cycles, deductions/additions)**
- [x] **Invoices & Taxes (4-tab: Commission, Ads, Recovery, GSTR1 — with PDF download, ZIP bundle)**
- [x] **Approvals queue (AM-submitted ads/offers) with OTP verification**

### Rider/Delivery Platform
- [x] Registration/login, profile, document upload + verification
- [x] Training/onboarding modules (3 modules with lessons + quiz)
- [x] Online/offline toggle, GPS location tracking
- [x] Real-time order assignment + accept/reject
- [x] Batch/bundled orders (multi-pickup multi-drop)
- [x] Turn-by-turn navigation (Ola Maps)
- [x] Bicycle/Walking/Scooter/Bike/Car delivery mode
- [x] Zone preference (choose preferred delivery areas)
- [x] Earnings + daily/weekly payouts + Instant UPI Payout
- [x] Incentive & Badge system (8 badges, 4 tiers: Bronze→Diamond)
- [x] Safety Suite (SOS button, emergency contacts, ride sharing)
- [x] Proof of Delivery (photo capture)
- [x] Multi-stop trip tracking
- [x] Demand Heat Map (live order hotspots)
- [x] Smart Earnings Estimator
- [x] Cash Reconciliation (COD tracking + deposit)
- [x] EV Charging Stations map
- [x] Dynamic Surge Pricing (time + demand-based)
- [x] Regional Festivals & Campaigns engine
- [x] Order item display in incoming delivery + active delivery views
- [x] **Dashboard (metrics, time-slot bar chart, top restaurants, period comparison)**
- [x] **History (order list, IST timeline, pay breakdown, paid/unpaid badge)**
- [x] **Payout cycles (daily listing, per-order detail)**
- [x] **Profile/KYC (Partner ID, KYC status, document upload with camera/gallery, expiry tracking)**
- [x] **Distance-based pay calculation (₹12/km, 200m/900m boundary logic)**
- [x] **5-tab shell (Dashboard, History, Live, Payout, Profile)**

### Admin Platform
- [x] KPI Dashboard, Order management, Rescue Center
- [x] Rider management + document verification + payouts
- [x] Venue/restaurant management + onboarding approval
- [x] Review moderation, Complaint management
- [x] Coupons, Banners, Global Categories, Cities
- [x] GST reports, Invoices, Vendor payments
- [x] Accounting dashboard + reports
- [x] Fraud detection, Ranking configuration
- [x] Multi-city expansion toolkit
- [x] Notification campaigns (push)
- [x] Granular role-based access (super_admin, city_manager, support_agent, finance_manager, **account_manager**)
- [x] Admin Audit Log (every action logged)
- [x] Live Order Map / War Room (animated Leaflet map)
- [x] Data Export engine (Orders/Revenue/Riders Excel)
- [x] Membership toggle
- [x] System Configuration (key-value store)
- [x] **Payout Cycle Management (search, generate, mark paid, deductions/additions, CSV export)**
- [x] **Invoices & Taxes (4-tab: Commission, Ads, Recovery, GSTR1 — generate, approve, paid, PDF, ZIP, XLSX)**
- [x] **Account Manager management (CRUD, restaurant assignments, AM dashboard, submissions tracker)**
- [x] **Rider KYC Review (document approve/reject/verify workflow with rejection reasons)**
- [x] **Rider Daily Cycle Payouts (list, filter, mark paid with UTR + payment mode)**
- [x] **Org Chart Builder (variable-depth geography-scoped org units, role assignment)**
- [x] **Approval Thresholds (DOA matrix CRUD + change audit trail)**
- [x] **Approval Queue (workflow visualization + full audit-trail timeline)**
- [x] **SLA Dashboard (per-unit/per-entity SLA status)**
- [x] **Department approval pages: Finance, GstFiling, RiderPayout, RiderKyc, AmMarketing, AmAssignment, SupportRefund**

### Recently Built Modules (July 2026 Session)
| Module | Backend | Admin Vue | Restaurant App | Customer App | Rider App |
|--------|:---:|:---:|:---:|:---:|:---:|
| Menu Module Upgrade (variants, add-ons, packaging, images, global cats, name_local, reel_url) | ✅ | ✅ | ✅ | ✅ (display) | ✅ (items) |
| Finance Payout Cycles (weekly deductions, commission, mark paid, UTR) | ✅ | ✅ | ✅ | — | — |
| Finance Invoices & Taxes (commission/ads/recovery invoices, CGST/SGST 9%, PDF, ZIP, GSTR1 XLSX) | ✅ | ✅ | ✅ | — | — |
| Offers/Promos Checkout Integration (DiscountCalculationService, promo input, cart discount state) | ✅ | ✅ | — | ✅ | — |
| Sponsored Ads Auction (GSP ranking, budget pacing, impression/click tracking, auto-pause scheduler) | ✅ | ✅ | ✅ | ✅ | — |
| Account Manager Approval Flow (AM CRUD, restaurant assignments, OTP approvals, audit log) | ✅ | ✅ | ✅ | — | — |
| Reporting Module (Live Tracking weekday-comparison, Business Reports trend table, CSV export, 14K metric rows across 8 restaurants) | ✅ | ✅ | ✅ | — | — |
| **Rider Dashboard — metrics, time-slot chart, top restaurants, period comp** | ✅ | — | — | — | ✅ |
| **Rider History — order list, IST timeline, pay breakdown, paid badge** | ✅ | — | — | — | ✅ |
| **Rider Payout Cycles — daily listing, per-order detail** | ✅ | — | — | — | ✅ |
| **Rider Profile/KYC — Partner ID, KYC status, document upload, expiry tracking** | ✅ | — | — | — | ✅ |
| **Rider Pay Calculator — ₹12/km, 200m/900m boundary logic** | ✅ | — | — | — | ✅ |
| **Rider 5-tab Shell — Dashboard, History, Live, Payout, Profile** | — | — | — | — | ✅ |
| **Admin Rider KYC Review — document approve/reject/verify** | ✅ | ✅ | — | — | — |
| **Admin Rider Cycle Payouts — list, filter, pay with UTR** | ✅ | ✅ | — | — | — |
| **Customer Web 3 Screens — FileComplaint, MyComplaints, ScratchCardHistory** | ✅ | — | — | ✅ | — |

### Org Hierarchy + Approval Workflow Engine (Aug 1–3 2026) — Generic engine, ported to all 4 departments
| Capability | Backend | Admin Web | Status |
|-----------|:---:|:---:|:---:|
| Generic `OrgUnit`/`OrgRole` — variable-depth, geography-scoped hierarchy | ✅ | OrgChartBuilder | ✅ |
| `ApprovalThresholdConfig` (DOA matrix) — admin-editable, per dept/unit/entity | ✅ | ApprovalThresholds | ✅ |
| Threshold immutability (§10): config changes never retroactively alter in-flight items (pinned at landing) | ✅ | — | ✅ |
| Engine: originate → fast-path approve → escalate → **send-back to ANY level** → resubmit → reject | ✅ | ApprovalQueue | ✅ |
| SLA auto-escalation (`approvals:check-sla`, every 15 min) + vacancy/backup handling | ✅ | SlaDashboard | ✅ |
| Full audit trail — every step (who/org/comment/timestamp), threshold changes audited | ✅ | Queue timeline + /approval-thresholds/audit | ✅ |
| Real users assigned to org roles; **no super_admin bypass** — only assigned reviewer can act | ✅ | — | ✅ |
| Finance: vendor payouts, invoices, recoveries, GST filings | ✅ | FinanceApprovals + GstFilingApprovals | ✅ |
| Rider Ops: rider payouts, KYC verification (5 docs) | ✅ | RiderPayoutApprovals + RiderKycApprovals | ✅ |
| Account Management: sponsored listings, offers, **geography-routed restaurant→AM assignments** | ✅ | AmMarketingApprovals + AmAssignmentApprovals | ✅ |
| Support: complaint refunds (wallet credit + ledger + order deduction) | ✅ | SupportRefundApprovals | ✅ |
| Smoke suite: 19 suites / **93/93 PASS** | ✅ | — | ✅ |
| Live-verified end-to-end on prod (multi-user approval, blocking wrong reviewer, GST, KYC, AM assignment) | ✅ | — | ✅ |

### Mobile Bug Fixes (3 Aug 2026) — all 3 apps rebuilt + installed on Vivo V2502i
| App | Issue | Fix |
|-----|-------|-----|
| Customer | Restaurant/dish images blank | api-gateway `/images/*` asset proxy (no `/api` prefix) + `sanitizeImageUrl` resolves relative paths to `https://catalog.utterlazy.com`; restaurant logos backfilled in DB |
| Restaurant | Reporting both tabs "bad response" | API verified working — stale phone token; fixed via fresh reinstall + re-login |
| Restaurant | Menu wizard "Next" stuck on Basics | `PageController` was recreated on every rebuild (declared inside `StatefulBuilder`) — moved out; tabs now advance |
| Restaurant | Earnings tab (payout done) | Tab removed from bottom nav (Payouts/Invoices/GST remain) |
| Rider | Dashboard "Failed to load" | Dashboard/History/Payout screens created bare `ApiClient()` (no token) → now use shared `apiClientProvider` carrying auth token |

### Infrastructure Fixes (July 2026 Session)
- [x] Logo optimized (2MB → 22KB) and deployed across all apps
- [x] Reverb WebSocket working (pcntl, port 8081, Pusher SDK)
- [x] Docker image committed (1.36GB with pcntl + reverb + pusher)
- [x] Queue worker fixed (redis → database driver)
- [x] Scheduler fixed (while loop instead of cron)
- [x] 22 pending migrations resolved
- [x] Boot recovery spam removed from AppServiceProvider
- [x] PHP lint: 447/447 files pass
- [x] All 3 Flutter APKs built and synced to server
- [x] dompdf installed for PDF invoice generation
- [x] Rider migrations (DailyRiderMetric, RiderPayoutCycle) — pending server apply

### Infrastructure Fixes (3 Aug 2026) — Notifications + Crash Recovery
- [x] **Notifications were broken**: app pushed to Redis (`QUEUE_CONNECTION=redis` via container env) but queue-worker drained MySQL — **42 broadcast jobs stuck, vendors/riders/admins got no real-time updates**. Worker recreated on Redis + DB env.
- [x] Queue-worker image was stale — **no `pusher`/`reverb` packages** (every job failed `Pusher\Pusher not found`). composer.json/lock synced + `composer install` in worker.
- [x] `config/broadcasting.php` was non-standard — missing `secret`, wrong `app_id` (`12345` vs server `utterlazy`), wrong host (`localhost`/`reverb` vs container `utterlazy-reverb`). Fixed to official Reverb shape, deployed to app + worker.
- [x] Scheduler container ran a stale image — **no `ApprovalWorkflowEngine.php`/`CheckApprovalSlas.php`, old `console.php` without `approvals:check-sla`** → SLA auto-escalation never ran. `docker commit` of live catalog-service → scheduler + reverb recreated from fresh image.
- [x] **Double schedule:run** (host cron + scheduler container) → host cron disabled; scheduler container is sole runner.
- [x] **Crash recovery verified live**: killed reverb/worker/scheduler PIDs → all auto-restarted (`unless-stopped`), reverb served 101 handshake after restart.
- [x] Broadcast chain end-to-end verified: app → Redis → worker → Reverb → clients, zero failures.
- [x] `failed_jobs` (57 stale) cleared.
- [x] api-gateway: `/images/*` asset proxy route added (forwards to catalog WITHOUT `/api` prefix) — public images now 200.

---

## ❌ NOT BUILT (Per Instructions)

- Postpaid/Khata System
- BNPL (Simpl, LazyPay)
- Multi-wallet (Paytm, Amazon Pay, Mobikwik)

## 🟡 DEFERRED

- Multi-language UI (backend ready, activate when needed)
- Membership (backend ready, admin toggle disabled by default)

---

## 📊 Feature Count by Stakeholder

| Stakeholder | Features |
|------------|----------|
| **Customer** | ~35+ features |
| **Restaurant/Vendor** | ~25+ features |
| **Rider** | ~20+ features |
| **Admin** | ~30+ features (incl. approval engine UI) |
| **Backend Engine** | ~20+ (approval engine, reporting, finance automation) |
| **Infrastructure** | ~18+ features |
| **Total** | **~70+ features** |

---

## 🚀 Deploy

```bash
# One-command deploy:
./deploy.sh
```

See `architecture.md`, `README.md`, `docker-compose.prod.yml`, and `nginx.conf` for details.
