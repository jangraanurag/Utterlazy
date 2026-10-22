"""Trainer tests with synthetic, separable data (stdlib only)."""

import csv
import json
import math
import os
import random
import sys
import tempfile
import unittest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from trainer import (  # noqa: E402
    FEATURES,
    DatasetError,
    auc,
    load_csv,
    predict_one,
    save_model,
    train,
)


def synthetic_rows(n=500, seed=7):
    rng = random.Random(seed)
    rows = []
    for i in range(n):
        bayes = rng.uniform(3.4, 4.9)
        distance = rng.uniform(0.4, 9.0)
        orders = rng.randint(0, 120)
        prep = rng.uniform(10, 40)
        cancel = rng.uniform(0, 0.2)
        price = rng.uniform(80, 700)
        is_new = 1 if rng.random() < 0.25 else 0
        rating_count = rng.randint(0, 400)

        logit = 3.0 * (bayes - 4.1) - 0.45 * distance + 0.012 * orders - 0.02 * prep
        label = 1 if rng.random() < 1 / (1 + math.exp(-logit)) else 0

        rows.append({
            "label": label,
            "restaurant_id": f"r-{i % 120}",
            "city_id": "c-1",
            "bayes_rating": round(bayes, 4),
            "rating_count": rating_count,
            "distance_km": round(distance, 4),
            "prep_time_minutes": round(prep, 2),
            "order_count_7d": orders,
            "cancellation_rate": round(cancel, 4),
            "avg_price": round(price, 2),
            "is_new": is_new,
        })

    return rows


def write_csv(path, rows):
    with open(path, "w", newline="", encoding="utf-8") as handle:
        writer = csv.DictWriter(handle, fieldnames=["label", "restaurant_id", "city_id", *FEATURES])
        writer.writeheader()
        for row in rows:
            writer.writerow(row)


class TrainerTests(unittest.TestCase):
    def test_train_learns_signal(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = os.path.join(tmp, "dataset.csv")
            write_csv(path, synthetic_rows())
            model = train(path, epochs=500, lr=0.2)

            self.assertEqual(model["version"], "ranker-logreg-v1")
            self.assertEqual(model["features"], FEATURES)
            self.assertGreater(model["metrics"]["test"]["accuracy"], 0.75)
            self.assertGreater(model["metrics"]["test"]["auc"], 0.78)

            out = os.path.join(tmp, "model.json")
            save_model(model, out)
            with open(out) as handle:
                loaded = json.load(handle)
            self.assertEqual(loaded["version"], "ranker-logreg-v1")

    def test_prediction_prefers_better_restaurant(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = os.path.join(tmp, "dataset.csv")
            write_csv(path, synthetic_rows())
            model = train(path, epochs=400, lr=0.2)

            good = {
                "bayes_rating": 4.7, "rating_count": 200, "distance_km": 1.0,
                "prep_time_minutes": 15, "order_count_7d": 80,
                "cancellation_rate": 0.01, "avg_price": 250, "is_new": 0,
            }
            bad = {
                "bayes_rating": 3.6, "rating_count": 200, "distance_km": 8.0,
                "prep_time_minutes": 38, "order_count_7d": 2,
                "cancellation_rate": 0.18, "avg_price": 600, "is_new": 1,
            }

            self.assertGreater(predict_one(model, good), predict_one(model, bad))

    def test_missing_columns_rejected(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = os.path.join(tmp, "bad.csv")
            with open(path, "w", newline="") as handle:
                writer = csv.writer(handle)
                writer.writerow(["label", "restaurant_id", "bayes_rating"])
                writer.writerow([1, "r-1", 4.5])

            with self.assertRaises(DatasetError):
                load_csv(path)

    def test_single_class_rejected(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = os.path.join(tmp, "one.csv")
            rows = synthetic_rows(40)
            for row in rows:
                row["label"] = 1
            write_csv(path, rows)
            with self.assertRaises(DatasetError):
                load_csv(path)

    def test_auc_perfect_and_chance(self):
        self.assertEqual(auc([1, 1, 0, 0], [0.9, 0.8, 0.2, 0.1]), 1.0)
        self.assertEqual(auc([1, 0, 1, 0], [0.5, 0.5, 0.5, 0.5]), 0.5)


if __name__ == "__main__":
    unittest.main(verbosity=2)
