"""Guardrail evaluation tests with synthetic models."""

import json
import os
import sys
import tempfile
import unittest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from evaluate import evaluate  # noqa: E402
from trainer import FEATURES, save_model, train  # noqa: E402
from test_trainer import synthetic_rows, write_csv  # noqa: E402


class GuardrailTests(unittest.TestCase):
    def _fixture(self, tmp):
        data = os.path.join(tmp, "data.csv")
        write_csv(data, synthetic_rows(400))
        model = train(data, epochs=400, lr=0.2)
        model_path = os.path.join(tmp, "model.json")
        save_model(model, model_path)
        return data, model, model_path

    def test_good_model_passes(self):
        with tempfile.TemporaryDirectory() as tmp:
            data, _, model_path = self._fixture(tmp)
            result = evaluate(data, model_path, min_auc=0.6)
            self.assertTrue(result["passed"])
            self.assertGreaterEqual(result["auc"], 0.6)

    def test_same_model_as_baseline_fails_margin(self):
        with tempfile.TemporaryDirectory() as tmp:
            data, _, model_path = self._fixture(tmp)
            result = evaluate(data, model_path, baseline_path=model_path, min_auc=0.6, margin=0.01)
            self.assertFalse(result["passed"])
            self.assertIsNotNone(result["baseline_auc"])

    def test_flat_model_fails_auc_gate(self):
        with tempfile.TemporaryDirectory() as tmp:
            data, _, _ = self._fixture(tmp)
            flat = {
                "version": "ranker-logreg-v1",
                "features": FEATURES,
                "weights": [0.0] * len(FEATURES),
                "bias": 0.0,
                "means": {f: 0.0 for f in FEATURES},
                "stds": {f: 1.0 for f in FEATURES},
                "metrics": {"test": {"accuracy": 0.7, "auc": 0.8}},
                "dataset_rows": 400,
            }
            flat_path = os.path.join(tmp, "flat.json")
            with open(flat_path, "w") as handle:
                json.dump(flat, handle)

            result = evaluate(data, flat_path, min_auc=0.6)
            self.assertFalse(result["passed"])
            self.assertLess(result["auc"], 0.6)


if __name__ == "__main__":
    unittest.main(verbosity=2)
