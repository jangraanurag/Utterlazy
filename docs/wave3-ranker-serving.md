# 🧪 Wave 3 — Ranker model serving gate (A/B)

The trained model can now be served — but only behind **four** safety gates. Default is the
deterministic policy; nothing changes unless a model is mounted and flags are set.

## Gates

1. `RANKER_MODEL_ENABLED=true` (or `RANKER_SHADOW_ENABLED=true`).
2. Model file exists at `RANKER_MODEL_PATH` (e.g. `/app/model.json`).
3. `load_model()` validates:
   - exact feature list (`bayes_rating, rating_count, distance_km, prep_time_minutes,
     order_count_7d, cancellation_rate, avg_price, is_new`),
   - weights/scaling sizes,
   - **test AUC ≥ 0.60 and accuracy ≥ 0.55**.
   Any failure → `policy_fallback` (logged, never crashes).
4. Assignment bucket: `sha256("ranker-v1|<assignment_key>") % 100 < RANKER_MODEL_SHARE_PERCENT`.
   No key → policy.

## Modes

| Mode | Order used | Extra response |
|---|---|---|
| `policy` | weighted policy | — |
| `model` | model scores | `experiment` |
| `shadow_policy` | policy | `shadow` (model ranking, not shown to users) |
| `policy_fallback` | policy | `experiment.model_available=false` |

`/rank` response now includes `scoring`, `experiment`, and `shadow`.

`assignment_key` is caller-supplied (use user id); the Laravel endpoint accepts it as a query
parameter and passes it through. It is **not** a model switch — clients cannot force `model`.

## Enable

```bash
# mount the trained model read-only, then set flags on the ranker service
RANKER_MODEL_PATH=/app/model.json
RANKER_MODEL_ENABLED=true
RANKER_MODEL_SHARE_PERCENT=10
RANKER_SHADOW_ENABLED=true      # optional: log model vs policy without exposure
docker compose -f docker-compose.prod.yml --profile ranker up -d ranker
```

Compose already wires the flags; add the model mount before enabling:

```yaml
volumes:
  - ./infra/ranker/model.json:/app/model.json:ro
```

## Measuring before ramping

- Run shadow first (`MODEL_ENABLED=false`, `SHADOW_ENABLED=true`) and compare `shadow.items`
  with the returned policy ranking in your analytics pipeline.
- Ramp share 10 → 25 → 50 only when guardrails hold: click-through, order conversion,
  cancellation rate, delivery time, and new-restaurant exposure.
- Keep the policy path available at all times; `policy_fallback` is the automatic kill switch.

## Tests

`services/ranker/tests/test_model.py` — 9 checks: load/score, feature mismatch, low AUC/accuracy,
missing file, `is_new` rule, deterministic buckets, mode matrix, model ordering.
API test asserts the default response stays `scoring: policy`.

## Not done

- online metric pipeline and automatic rollback
- per-city/per-cuisine experiment stratification
- signed model artifacts / registry
