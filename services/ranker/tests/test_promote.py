"""Promotion/rollback tests with synthetic models."""

import json
import os
import sys
import tempfile
import unittest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from app.model import FEATURES, load_model  # noqa: E402
from promote import promote, rollback  # noqa: E402
from trainer import save_model, train  # noqa: E402
from test_trainer import synthetic_rows, write_csv  # noqa: E402


def flat_model():
    return {
        "version": "flat-baseline",
        "features": FEATURES,
        "weights": [0.0] * len(FEATURES),
        "bias": 0.0,
        "means": {f: 0.0 for f in FEATURES},
        "stds": {f: 1.0 for f in FEATURES},
        "metrics": {"test": {"accuracy": 0.7, "auc": 0.8}},
        "dataset_rows": 400,
    }


class PromoteTests(unittest.TestCase):
    def _fixture(self, tmp):
        data = os.path.join(tmp, "data.csv")
        write_csv(data, synthetic_rows(400))
        candidate = os.path.join(tmp, "candidate.json")
        save_model(train(data, epochs=400, lr=0.2), candidate)
        served = os.path.join(tmp, "served.json")
        with open(served, "w") as handle:
            json.dump(flat_model(), handle)
        return data, candidate, served

    def test_promotes_better_model_and_backs_up(self):
        with tempfile.TemporaryDirectory() as tmp:
            data, candidate, served = self._fixture(tmp)
            result, promoted = promote(candidate, served, data)

            self.assertTrue(promoted)
            self.assertTrue(result["passed"])
            self.assertTrue(os.path.exists(served + ".prev"))
            with open(candidate) as a, open(served) as b:
                self.assertEqual(json.load(a)["version"], json.load(b)["version"])

    def test_refuses_identical_model(self):
        with tempfile.TemporaryDirectory() as tmp:
            data, candidate, _ = self._fixture(tmp)
            served = candidate  # same file as baseline: cannot beat margin
            before = open(served).read()
            result, promoted = promote(candidate, served, data)

            self.assertFalse(promoted)
            self.assertFalse(result["passed"])
            self.assertEqual(before, open(served).read())

    def test_rollback_restores_previous(self):
        with tempfile.TemporaryDirectory() as tmp:
            data, candidate, served = self._fixture(tmp)
            original = open(served).read()
            promote(candidate, served, data)
            self.assertNotEqual(original, open(served).read())

            rollback(served)
            self.assertEqual(original, open(served).read())
            self.assertTrue(os.path.exists(served + ".rejected"))
            self.assertEqual(load_model(served).version, "flat-baseline")


if __name__ == "__main__":
    unittest.main(verbosity=2)
