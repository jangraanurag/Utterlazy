# 🧠 Wave 3 — Organic ranker

Fair relevance for app/web discovery. Unpaid restaurants are always included; paid partners get
a small (+0.05), disclosed preference and a `sponsored` flag so the UI can label them.

## Components

| Piece | Status |
|---|---|
| `services/ranker/app/ranking.py` — dependency-free scoring engine | ✅ |
| `services/ranker/app/main.py` — FastAPI `POST /rank`, `GET /health` | ✅ |
| Unit tests (13 core) + API tests (4) | ✅ 17/17 |
| Dockerfile + `--profile ranker` in dev/prod compose | ✅ |
| Laravel `RankerClient` + `GET /api/recommendations/ranked-organic` (flag-gated, PHP fallback) | ✅ |
| Feature store (7-day orders, cancellation rate in one query; ratings count) | ✅ basic |
| Dataset exporter + baseline trainer (offline, not served) | ✅ `docs/wave3-ranker-training.md` |
| ONDC adapter, incident console | ⬜ |

## Signals

| Signal | Weight | Notes |
|---|---|---|
| Bayesian rating | 0.35 | prior 4.0 / weight 20 — a 5★ with 2 ratings does not win |
| Distance decay | 0.20 | linear inside `radius_km` |
| Prep time | 0.15 | faster wins |
| 7-day popularity | 0.15 | log-scaled, capped |
| Reliability | 0.10 | cancellation-rate penalty |
| Price preference | 0.05 | `low|medium|high|any` bands |
| New-restaurant exploration | +0.06 | `is_new` or <20 ratings; never a penalty |

Tie-break: `restaurant_id` ascending (deterministic). Cuisine diversity caps top slots
(`max_per_cuisine`), then backfills so the page is full.

## Paid preference policy

- Laravel sends `sponsored` from `restaurants.is_sponsored`.
- The engine adds `PAID_BOOST = 0.05` to paid candidates and exposes it in `breakdown.paid_boost`.
- Unpaid candidates are never removed; they simply rank with their organic score.
- The response marks `sponsored` so the client must show a Sponsored label.
- This is preference, not exclusion. Ads auctions still exist for dedicated placements.

## Run

```bash
docker compose -f docker-compose.prod.yml --profile ranker up -d ranker
RANKER_ENABLED=true docker compose -f docker-compose.prod.yml up -d catalog-service
curl -fsS http://127.0.0.1:8010/health
curl -s "http://127.0.0.1:8001/api/recommendations/ranked-organic?lat=28.61&lng=77.21&limit=10"
```

Response includes `source` and `model_version`. When the ranker is disabled/unreachable,
Laravel returns deterministic local ranking (`php_fallback`) — discovery never hard-fails.

## Test

```bash
cd services/ranker
python3 -m unittest discover -s tests -v      # core only (no deps)
pip install -r requirements.txt -r requirements-dev.txt
python3 -m unittest discover -s tests -v      # + API tests
```

CI job `ranker-tests` runs both.
