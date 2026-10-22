"""Dependency-free logistic-regression trainer for the organic ranker.

Reads the CSV produced by `php artisan ranker:export-dataset`, learns a linear
relevance model, and writes a versioned JSON model with metrics.

This is an offline experimentation baseline, not a production ranker. It exists
so feature/label pipelines can be validated end-to-end before adding heavier
models (LightGBM/two-tower) behind the same model contract.
"""

from __future__ import annotations

import csv
import hashlib
import json
import math
import random
from datetime import datetime, timezone
from pathlib import Path

FEATURES = [
    "bayes_rating",
    "rating_count",
    "distance_km",
    "prep_time_minutes",
    "order_count_7d",
    "cancellation_rate",
    "avg_price",
    "is_new",
]

MODEL_VERSION = "ranker-logreg-v1"


class DatasetError(ValueError):
    pass


def load_csv(path: str | Path) -> tuple[list[dict], list[int], list[str]]:
    """Return (rows, labels, restaurant_ids). Raises DatasetError on bad input."""
    rows: list[dict] = []
    labels: list[int] = []
    restaurants: list[str] = []

    with open(path, newline="", encoding="utf-8") as handle:
        reader = csv.DictReader(handle)
        if reader.fieldnames is None:
            raise DatasetError("missing CSV header")

        missing = [f for f in FEATURES + ["label"] if f not in reader.fieldnames]
        if missing:
            raise DatasetError("missing columns: " + ", ".join(missing))

        for line_no, record in enumerate(reader, start=2):
            try:
                label = int(float(record["label"]))
            except (TypeError, ValueError) as exc:
                raise DatasetError(f"line {line_no}: invalid label") from exc

            if label not in (0, 1):
                raise DatasetError(f"line {line_no}: label must be 0 or 1")

            try:
                row = {f: float(record[f]) for f in FEATURES}
            except (TypeError, ValueError) as exc:
                raise DatasetError(f"line {line_no}: non-numeric feature") from exc

            rows.append(row)
            labels.append(label)
            restaurants.append(str(record.get("restaurant_id", "")))

    if not rows:
        raise DatasetError("empty dataset")

    if len(set(labels)) < 2:
        raise DatasetError("dataset needs both positive and negative labels")

    return rows, labels, restaurants


def split_by_restaurant(rows, labels, restaurants, test_ratio=0.2, salt="v0"):
    """Group-aware split so the same restaurant never leaks across train/test."""
    train_idx, test_idx = [], []

    for i, restaurant in enumerate(restaurants):
        digest = hashlib.sha256(f"{salt}|{restaurant}".encode()).digest()
        bucket = digest[0] % 100
        (test_idx if bucket < test_ratio * 100 else train_idx).append(i)

    if not train_idx or not test_idx:
        cut = max(1, int(len(rows) * (1 - test_ratio)))
        train_idx, test_idx = list(range(cut)), list(range(cut, len(rows)))

    return train_idx, test_idx


def standardize(train_rows):
    means, stds = {}, {}
    for feature in FEATURES:
        values = [row[feature] for row in train_rows]
        mean = sum(values) / len(values)
        variance = sum((v - mean) ** 2 for v in values) / len(values)
        means[feature] = mean
        stds[feature] = math.sqrt(variance) or 1.0
    return means, stds


def _scale(row, means, stds):
    return [(row[f] - means[f]) / stds[f] for f in FEATURES]


def _sigmoid(z):
    if z >= 0:
        return 1.0 / (1.0 + math.exp(-z))
    exp = math.exp(z)
    return exp / (1.0 + exp)


def train_logistic(rows, labels, epochs=600, lr=0.1, l2=0.001, seed=7):
    means, stds = standardize(rows)
    X = [_scale(row, means, stds) for row in rows]
    y = list(labels)

    weights = [0.0] * len(FEATURES)
    bias = 0.0
    rng = random.Random(seed)
    order = list(range(len(X)))
    n = len(X)
    last_loss = 0.0

    for _ in range(epochs):
        rng.shuffle(order)
        grad_w = [0.0] * len(FEATURES)
        grad_b = 0.0
        loss = 0.0

        for i in order:
            z = bias + sum(w * x for w, x in zip(weights, X[i]))
            p = min(max(_sigmoid(z), 1e-9), 1 - 1e-9)
            error = p - y[i]
            loss += -(y[i] * math.log(p) + (1 - y[i]) * math.log(1 - p))

            for j in range(len(FEATURES)):
                grad_w[j] += error * X[i][j]
            grad_b += error

        for j in range(len(FEATURES)):
            weights[j] -= lr * (grad_w[j] / n + l2 * weights[j])
        bias -= lr * (grad_b / n)
        last_loss = loss / n

    return {
        "weights": weights,
        "bias": bias,
        "means": means,
        "stds": stds,
        "loss": round(last_loss, 6),
    }


def predict_one(model, row):
    z = model["bias"] + sum(
        w * ((row[f] - model["means"][f]) / model["stds"][f])
        for w, f in zip(model["weights"], FEATURES)
    )
    return _sigmoid(z)


def auc(y_true, scores):
    """Rank-based AUC with average ranks for ties."""
    pairs = sorted(zip(scores, y_true), key=lambda p: p[0])
    ranks = [0.0] * len(pairs)
    i = 0
    while i < len(pairs):
        j = i
        while j + 1 < len(pairs) and pairs[j + 1][0] == pairs[i][0]:
            j += 1
        avg_rank = (i + j) / 2.0 + 1.0
        for k in range(i, j + 1):
            ranks[k] = avg_rank
        i = j + 1

    positives = sum(1 for _, y in pairs if y == 1)
    negatives = len(pairs) - positives
    if positives == 0 or negatives == 0:
        return 0.5

    positive_rank_sum = sum(r for r, (_, y) in zip(ranks, pairs) if y == 1)

    return (positive_rank_sum - positives * (positives + 1) / 2.0) / (positives * negatives)


def evaluate(model, rows, labels):
    scores = [predict_one(model, row) for row in rows]
    correct = sum(1 for s, y in zip(scores, labels) if (s >= 0.5) == (y == 1))
    accuracy = correct / len(rows) if rows else 0.0

    return {
        "rows": len(rows),
        "accuracy": round(accuracy, 4),
        "auc": round(auc(labels, scores), 4),
    }


def train(csv_path, epochs=600, lr=0.1, l2=0.001, test_ratio=0.2, seed=7):
    rows, labels, restaurants = load_csv(csv_path)
    train_idx, test_idx = split_by_restaurant(rows, labels, restaurants, test_ratio)

    train_rows = [rows[i] for i in train_idx]
    train_labels = [labels[i] for i in train_idx]
    test_rows = [rows[i] for i in test_idx]
    test_labels = [labels[i] for i in test_idx]

    fitted = train_logistic(train_rows, train_labels, epochs=epochs, lr=lr, l2=l2, seed=seed)

    model = {
        "version": MODEL_VERSION,
        "features": FEATURES,
        "weights": [round(w, 8) for w in fitted["weights"]],
        "bias": round(fitted["bias"], 8),
        "means": {k: round(v, 8) for k, v in fitted["means"].items()},
        "stds": {k: round(v, 8) for k, v in fitted["stds"].items()},
        "metrics": {
            "train": evaluate(fitted, train_rows, train_labels),
            "test": evaluate(fitted, test_rows, test_labels),
            "train_loss": fitted["loss"],
        },
        "trained_at": datetime.now(timezone.utc).isoformat(),
        "dataset_rows": len(rows),
    }

    return model


def save_model(model, path):
    Path(path).parent.mkdir(parents=True, exist_ok=True)
    with open(path, "w", encoding="utf-8") as handle:
        json.dump(model, handle, indent=2)
        handle.write("\n")
