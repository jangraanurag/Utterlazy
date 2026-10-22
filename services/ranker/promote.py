#!/usr/bin/env python3
"""Guardrailed model promotion / rollback.

Promotion only happens when evaluate.py passes (AUC gate + baseline margin).
The served model is backed up to `<served>.prev` before being replaced.
"""

from __future__ import annotations

import argparse
import os
import shutil
import sys

from evaluate import evaluate


def promote(candidate_path, served_path, csv_path, min_auc=0.6, margin=0.01):
    baseline = served_path if os.path.exists(served_path) else None
    result = evaluate(csv_path, candidate_path, baseline_path=baseline, min_auc=min_auc, margin=margin)

    if not result["passed"]:
        return result, False

    if baseline:
        shutil.copy2(served_path, served_path + ".prev")
    shutil.copy2(candidate_path, served_path)

    return result, True


def rollback(served_path):
    previous = served_path + ".prev"
    if not os.path.exists(previous):
        raise FileNotFoundError("no previous model to roll back to")

    if os.path.exists(served_path):
        shutil.copy2(served_path, served_path + ".rejected")

    shutil.copy2(previous, served_path)


def main() -> int:
    parser = argparse.ArgumentParser(description="Guardrailed ranker model promotion/rollback")
    parser.add_argument("--candidate")
    parser.add_argument("--served")
    parser.add_argument("--csv")
    parser.add_argument("--min-auc", type=float, default=0.6)
    parser.add_argument("--margin", type=float, default=0.01)
    parser.add_argument("--rollback", action="store_true")
    args = parser.parse_args()

    if not args.served:
        print("--served is required", file=sys.stderr)
        return 2

    if args.rollback:
        try:
            rollback(args.served)
        except FileNotFoundError as exc:
            print(f"rollback error: {exc}", file=sys.stderr)
            return 2
        print(f"rolled back {args.served}")
        return 0

    if not args.candidate or not args.csv:
        print("--candidate and --csv are required for promotion", file=sys.stderr)
        return 2

    result, promoted = promote(args.candidate, args.served, args.csv, args.min_auc, args.margin)
    print(
        f"candidate auc={result['auc']} baseline={result['baseline_auc']} "
        f"gate={'PASS' if result['passed'] else 'FAIL'} promoted={promoted}"
    )
    return 0 if promoted else 1


if __name__ == "__main__":
    raise SystemExit(main())
