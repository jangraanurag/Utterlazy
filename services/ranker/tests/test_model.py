"""Model loading, A/B gate, and scored ranking tests (stdlib only)."""

import json
import os
import sys
import tempfile
import unittest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from app.model import (  # noqa: E402
    FEATURES,
    ModelError,
    bucket_for,
    candidate_features,
    load_model,
    model_scorer,
    select_mode,
)
from app.ranking import Candidate, Options, rank  # noqa: E402


def good_candidate(rid="good"):
    return Candidate(
        restaurant_id=rid, rating=4.7, rating_count=200, distance_km=1.0,
        prep_time_minutes=15, order_count_7d=80, cancellation_rate=0.01,
        avg_price=250, cuisines=("north-indian",), is_new=False,
    )


def bad_candidate(rid="bad"):
    return Candidate(
        restaurant_id=rid, rating=3.6, rating_count=200, distance_km=8.0,
        prep_time_minutes=38, order_count_7d=2, cancellation_rate=0.18,
        avg_price=600, cuisines=("north-indian",), is_new=True,
    )


def write_model(path, *, auc=0.8, accuracy=0.7, features=None):
    model = {
        "version": "ranker-logreg-v1",
        "features": features or FEATURES,
        "weights": [1.0, 0.0, -0.5, -0.2, 0.3, -0.4, -0.1, 0.0],
        "bias": 0.0,
        "means": {f: 0.0 for f in FEATURES},
        "stds": {f: 1.0 for f in FEATURES},
        "metrics": {"train": {"accuracy": accuracy, "auc": auc},
                    "test": {"accuracy": accuracy, "auc": auc}},
        "trained_at": "2026-10-21T00:00:00Z",
        "dataset_rows": 500,
    }
    with open(path, "w", encoding="utf-8") as handle:
        json.dump(model, handle)
    return model


class ModelTests(unittest.TestCase):
    def test_load_and_score(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = os.path.join(tmp, "model.json")
            write_model(path)
            model = load_model(path)

            self.assertEqual(model.version, "ranker-logreg-v1")
            self.assertGreater(
                model.score(candidate_features(good_candidate())),
                model.score(candidate_features(bad_candidate())),
            )

    def test_feature_mismatch_rejected(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = os.path.join(tmp, "model.json")
            write_model(path, features=["bayes_rating", "distance_km"])
            with self.assertRaises(ModelError):
                load_model(path)

    def test_low_auc_rejected(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = os.path.join(tmp, "model.json")
            write_model(path, auc=0.5)
            with self.assertRaises(ModelError):
                load_model(path)

    def test_low_accuracy_rejected(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = os.path.join(tmp, "model.json")
            write_model(path, accuracy=0.5)
            with self.assertRaises(ModelError):
                load_model(path)

    def test_missing_model_rejected(self):
        with self.assertRaises(ModelError):
            load_model("/tmp/does-not-exist-ranker-model.json")

    def test_is_new_feature_rule(self):
        fresh = Candidate(
            restaurant_id="new", rating=4.2, rating_count=3, distance_km=0.5,
        )
        established = Candidate(
            restaurant_id="old", rating=4.2, rating_count=300, distance_km=0.5,
        )
        self.assertEqual(candidate_features(fresh)["is_new"], 1.0)
        self.assertEqual(candidate_features(established)["is_new"], 0.0)

    def test_bucket_is_deterministic_and_bounded(self):
        first = bucket_for("user-42")
        self.assertEqual(first, bucket_for("user-42"))
        self.assertGreaterEqual(first, 0)
        self.assertLess(first, 100)

    def test_mode_selection(self):
        self.assertEqual(select_mode(True, False, True, 5, 10), "model")
        self.assertEqual(select_mode(True, True, True, 50, 10), "shadow_policy")
        self.assertEqual(select_mode(True, False, True, 50, 10), "policy")
        self.assertEqual(select_mode(True, False, False, 5, 10), "policy_fallback")
        self.assertEqual(select_mode(False, False, False, None, 10), "policy")
        self.assertEqual(select_mode(True, False, True, None, 10), "policy")

    def test_model_ranking_prefers_better_restaurant(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = os.path.join(tmp, "model.json")
            write_model(path)
            model = load_model(path)

            result = rank(
                [bad_candidate(), good_candidate()],
                Options(limit=2),
                scorer=model_scorer(model),
            )
            self.assertEqual(result[0].restaurant_id, "good")
            self.assertIn("model", result[0].breakdown)


if __name__ == "__main__":
    unittest.main(verbosity=2)
