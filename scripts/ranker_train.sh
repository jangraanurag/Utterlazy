#!/usr/bin/env bash
#
# Export a labeled dataset from Laravel and train the ranker baseline.
# Offline only: the resulting model is NOT served by the ranker yet.
#
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "${ROOT}"

DAYS="${DAYS:-30}"
DATASET="${DATASET:-infra/ranker/dataset.csv}"
MODEL="${MODEL:-infra/ranker/model.json}"
PHP_BIN="${PHP_BIN:-php}"
PYTHON_BIN="${PYTHON_BIN:-python3}"

mkdir -p "$(dirname "${DATASET}")" "$(dirname "${MODEL}")"

(
  cd catalog-service
  "${PHP_BIN}" artisan ranker:export-dataset \
    --days="${DAYS}" \
    --out="${ROOT}/${DATASET}"
)

"${PYTHON_BIN}" services/ranker/train.py --csv "${DATASET}" --out "${MODEL}"

printf '\nDataset: %s\nModel:   %s\n' "${DATASET}" "${MODEL}"
printf 'This model is offline only; ranking.py does not load it yet.\n'
