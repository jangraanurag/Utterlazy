#!/usr/bin/env python3
"""Train the organic ranker baseline from a CSV exported by Laravel."""

from __future__ import annotations

import argparse
import sys

from trainer import DatasetError, save_model, train


def main() -> int:
    parser = argparse.ArgumentParser(description="Train the UtterLazy organic ranker baseline")
    parser.add_argument("--csv", required=True, help="dataset CSV from ranker:export-dataset")
    parser.add_argument("--out", default="model.json", help="model JSON output path")
    parser.add_argument("--epochs", type=int, default=600)
    parser.add_argument("--lr", type=float, default=0.1)
    parser.add_argument("--l2", type=float, default=0.001)
    args = parser.parse_args()

    try:
        model = train(args.csv, epochs=args.epochs, lr=args.lr, l2=args.l2)
    except DatasetError as exc:
        print(f"dataset error: {exc}", file=sys.stderr)
        return 2
    except FileNotFoundError:
        print(f"dataset not found: {args.csv}", file=sys.stderr)
        return 2

    save_model(model, args.out)

    train_metrics = model["metrics"]["train"]
    test_metrics = model["metrics"]["test"]
    print(f"rows={model['dataset_rows']} model={model['version']}")
    print(f"train accuracy={train_metrics['accuracy']} auc={train_metrics['auc']}")
    print(f"test  accuracy={test_metrics['accuracy']} auc={test_metrics['auc']}")
    print(f"wrote {args.out}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
