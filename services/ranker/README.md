# Organic ranker (Wave 3)

Ranks restaurants for **organic** app/web surfaces only. Sponsored inventory never enters
this service: a candidate with `sponsored: true` is rejected with HTTP 422.

## Run

```bash
pip install -r requirements.txt
uvicorn app.main:app --host 0.0.0.0 --port 8010
curl -s localhost:8010/health
```

## Test

```bash
python3 -m unittest discover -s tests -v
```

Core tests are dependency-free; API tests run when FastAPI is installed.

## Signals

| Signal | Weight | Notes |
|---|---|---|
| Bayesian rating | 0.35 | prior 4.0 / weight 20, resists 1-review 5★ |
| Distance decay | 0.20 | linear inside radius |
| Prep time | 0.15 | faster is better |
| 7-day popularity | 0.15 | log-scaled, capped |
| Reliability | 0.10 | cancellation-rate penalty |
| Price preference | 0.05 | low/medium/high bands; neutral for `any` |
| New-restaurant exploration | +0.06 | `is_new` or <20 ratings; never a penalty |

Deterministic tie-break on `restaurant_id`. Cuisine diversity caps top slots
(`max_per_cuisine`), then backfills so the page is full.

## Why not sponsored

Paid placement is an auction in the ads service, labelled "Sponsored". Mixing it here would
silently corrupt organic relevance and violate the product promise. The engine refuses it.
