#!/usr/bin/env python3
"""Ranker guardrail evaluation.

Loads a candidate model and evaluates it on a labeled CSV. Optionally compares
against the current/baseline model; exits non-zero when gates fail.

Usage:
  python evaluate.py --csv data.csv --model model.json [--baseline old.json]
                     [--min-auc 0.6] [--margin 0.01] [--json]
"""

from __future__ import annotations

import argparse
import json
import sys

from app.model import ModelError, load_model
from trainer import DatasetError, auc, load_csv


def evaluate(csv_path, model_path, baseline_path=None, min_auc=0.6, margin=0.01):
    rows, labels, _ = load_csv(csv_path)
    model = load_model(model_path)

    scores = [model.score(row) for row in rows]
    accuracy = sum(1 for s, y in zip(scores, labels) if (s >= 0.5) == (y == 1)) / len(rows)
    model_auc = auc(labels, scores)

    baseline_auc = None
    if baseline_path:
        baseline = load_model(baseline_path)
        baseline_scores = [baseline.score(row) for row in rows]
        baseline_auc = auc(labels, baseline_scores)

    passed = model_auc >= min_auc and (
        baseline_auc is None or model_auc >= baseline_auc + margin
    )

    return {
        "model_version": model.version,
        "dataset_rows": len(rows),
        "accuracy": round(accuracy, 4),
        "auc": round(model_auc, 4),
        "min_auc": min_auc,
        "baseline_auc": None if baseline_auc is None else round(baseline_auc, 4),
        "margin": margin,
        "passed": passed,
    }


def main() -> int:
    parser = argparse.ArgumentParser(description="Evaluate ranker model guardrails")
    parser.add_argument("--csv", required=True)
    parser.add_argument("--model", required=True)
    parser.add_argument("--baseline")
    parser.add_argument("--min-auc", type=float, default=0.6)
    parser.add_argument("--margin", type=float, default=0.01)
    parser.add_argument("--json", action="store_true")
    args = parser.parse_args()

    try:
        result = evaluate(args.csv, args.model, args.baseline, args.min_auc, args.margin)
    except (DatasetError, ModelError, FileNotFoundError) as exc:
        print(f"guardrail error: {exc}", file=sys.stderr)
        return 2

    if args.json:
        print(json.dumps(result, indent=2))
    else:
        print(
            f"model={result['model_version']} rows={result['dataset_rows']} "
            f"accuracy={result['accuracy']} auc={result['auc']} "
            f"baseline_auc={result['baseline_auc']} gate={'PASS' if result['passed'] else 'FAIL'}"
        )

    return 0 if result["passed"] else 1


if __name__ == "__main__":
    raise SystemExit(main())
