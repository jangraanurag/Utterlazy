"""API-level tests. Skipped when FastAPI is not installed."""

import os
import sys
import unittest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

try:
    from fastapi.testclient import TestClient

    from app.main import app

    HAS_FASTAPI = True
except Exception:  # pragma: no cover - depends on environment
    HAS_FASTAPI = False


def payload(restaurants):
    return {
        "candidates": restaurants,
        "options": {"radius_km": 10, "limit": 5, "price_pref": "any", "max_per_cuisine": 2},
    }


def restaurant(rid, **overrides):
    base = {
        "restaurant_id": rid,
        "rating": 4.0,
        "rating_count": 100,
        "distance_km": 2.0,
        "prep_time_minutes": 20,
        "order_count_7d": 20,
        "cancellation_rate": 0.02,
        "avg_price": 250,
        "cuisines": ["north-indian"],
        "is_new": False,
        "sponsored": False,
        "is_open": True,
    }
    base.update(overrides)
    return base


@unittest.skipUnless(HAS_FASTAPI, "fastapi not installed")
class ApiTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.client = TestClient(app)

    def test_health(self):
        response = self.client.get("/health")
        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.json()["model_version"], "organic-ranker-v1")

    def test_rank_orders_by_relevance(self):
        response = self.client.post("/rank", json=payload([
            restaurant("low", rating=3.2),
            restaurant("high", rating=4.9),
        ]))
        self.assertEqual(response.status_code, 200)
        items = response.json()["items"]
        self.assertEqual(items[0]["restaurant_id"], "high")
        self.assertEqual(response.json()["model_version"], "organic-ranker-v1")
        self.assertEqual(response.json()["scoring"], "policy")

    def test_sponsored_preferred_but_visible(self):
        response = self.client.post("/rank", json=payload([
            restaurant("paid", sponsored=True),
            restaurant("organic", sponsored=False),
        ]))
        self.assertEqual(response.status_code, 200)
        items = response.json()["items"]
        self.assertEqual(items[0]["restaurant_id"], "paid")
        self.assertEqual(len(items), 2)
        self.assertTrue(items[0]["sponsored"])

    def test_validation_error(self):
        bad = payload([restaurant("bad", rating=9.0)])
        self.assertEqual(self.client.post("/rank", json=bad).status_code, 422)


if __name__ == "__main__":
    unittest.main(verbosity=2)
