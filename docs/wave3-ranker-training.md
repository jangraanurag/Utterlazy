# 🧪 Wave 3 — Ranker dataset + training pipeline

Offline pipeline for the organic ranker. It proves the **feature → label → model** loop
end-to-end. The trained model is **not served** by `ranking.py` yet; serving requires
offline evaluation and an A/B gate first.

## 1. Export a labeled dataset (Laravel)

```bash
cd catalog-service
php artisan ranker:export-dataset --days=30 --negatives=3 \
  --out=../infra/ranker/dataset.csv
```

Per delivered order (last N days, with delivery coordinates):

- **positive** (label 1): the ordered restaurant
- **negatives** (label 0): up to N same-city active restaurants the user did **not** order from

Features (same order as `ranking.py`):

```
bayes_rating, rating_count, distance_km, prep_time_minutes,
order_count_7d, cancellation_rate, avg_price, is_new
```

**v0 caveat:** popularity/cancellation are computed at export time, not order time. This can
leak future information. Use ClickHouse time-travel features before trusting production
metrics. The exporter is explicitly for experimentation.

## 2. Train the baseline (Python, no dependencies)

```bash
python3 services/ranker/train.py --csv infra/ranker/dataset.csv \
  --out infra/ranker/model.json
```

- Group-aware split by `restaurant_id` (no restaurant leaks across train/test).
- Standardized logistic regression (gradient descent, L2).
- Metrics: accuracy + rank-based AUC, train loss, row counts.
- Output: `ranker-logreg-v1` JSON with `features`, `weights`, `bias`, `means`, `stds`,
  `metrics`, `trained_at`.

One command wrapper:

```bash
./scripts/ranker_train.sh          # DAYS=30 by default
```

## 3. Contract

| Field | Meaning |
|---|---|
| `version` | model contract version (`ranker-logreg-v1`) |
| `features` | exact ordered feature list |
| `means` / `stds` | train-set scaling |
| `weights` / `bias` | linear scorer |
| `metrics` | train/test accuracy + AUC |
| `dataset_rows` | provenance |

Any future model (LightGBM/two-tower) must emit the same top-level contract so the serving
adapter can swap implementations without changing callers.

## Verification

```bash
cd services/ranker
python3 -m unittest discover -s tests -v   # 22 tests: ranking, API, trainer
```

Trainer tests use synthetic separable data and assert test accuracy > 0.75 and AUC > 0.78,
plus a preference check between a clearly good and clearly bad restaurant.

## Guardrails (built)

```bash
python3 services/ranker/evaluate.py --csv infra/ranker/dataset.csv \
  --model infra/ranker/model.json --min-auc 0.6 --baseline infra/ranker/previous.json
```

- Fails (exit 1) when AUC < `--min-auc` or when it does not beat the baseline by `--margin`.
- Compares candidate vs baseline on the same dataset; use it before swapping served models.
- Does not auto-promote or auto-rollback; deployment still moves the model file/env explicitly.

## Not done (deliberately)

- Serving the trained model in `ranking.py`
- Time-travel feature store (ClickHouse)
- Online A/B assignment and guardrail metrics
- Model registry/rollback beyond the JSON file
