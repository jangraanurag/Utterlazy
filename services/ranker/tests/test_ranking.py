"""Tests for the organic ranker. Standard library only.

Run: python3 -m unittest discover -s tests -v   (from services/ranker)
"""

import os
import sys
import unittest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from app.ranking import (  # noqa: E402
    Candidate,
    Options,
    MODEL_VERSION,
    rank,
)


def candidate(rid: str, **overrides) -> Candidate:
    base = dict(
        restaurant_id=rid,
        rating=4.0,
        rating_count=100,
        distance_km=2.0,
        prep_time_minutes=20.0,
        order_count_7d=20,
        cancellation_rate=0.02,
        avg_price=250.0,
        cuisines=("north-indian",),
        is_new=False,
        sponsored=False,
        is_open=True,
    )
    base.update(overrides)
    return Candidate(**base)


class RankingTests(unittest.TestCase):
    def test_model_version(self):
        self.assertEqual(MODEL_VERSION, "organic-ranker-v1")

    def test_higher_rating_wins(self):
        result = rank([candidate("low", rating=3.5), candidate("high", rating=4.8)],
                      Options(limit=2))
        self.assertEqual(result[0].restaurant_id, "high")

    def test_closer_wins_when_else_equal(self):
        result = rank([candidate("far", distance_km=8.0), candidate("near", distance_km=1.0)],
                      Options(limit=2))
        self.assertEqual(result[0].restaurant_id, "near")

    def test_bayesian_smoothing(self):
        # 5.0 with 2 ratings must not beat 4.6 with 500 ratings.
        result = rank([
            candidate("tiny", rating=5.0, rating_count=20),
            candidate("proven", rating=4.6, rating_count=500),
        ], Options(limit=2))
        self.assertEqual(result[0].restaurant_id, "proven")

    def test_new_restaurant_gets_exploration_boost(self):
        result = rank([
            candidate("established", is_new=False),
            candidate("new", is_new=True),
        ], Options(limit=2))
        by_id = {item.restaurant_id: item for item in result}
        self.assertGreater(by_id["new"].score, by_id["established"].score)
        self.assertGreater(by_id["new"].breakdown["exploration"], 0)
        self.assertEqual(by_id["established"].breakdown["exploration"], 0)

    def test_sponsored_preferred_but_visible(self):
        result = rank([
            candidate("organic", sponsored=False),
            candidate("paid", sponsored=True),
        ], Options(limit=2))
        by_id = {item.restaurant_id: item for item in result}
        self.assertEqual(len(result), 2)
        self.assertGreater(by_id["paid"].score, by_id["organic"].score)
        self.assertEqual(by_id["paid"].breakdown["paid_boost"], 0.05)
        self.assertTrue(by_id["paid"].sponsored)
        self.assertFalse(by_id["organic"].sponsored)

    def test_closed_and_far_are_filtered(self):
        result = rank([
            candidate("closed", is_open=False),
            candidate("far", distance_km=25.0),
            candidate("ok"),
        ], Options(limit=5, radius_km=10.0))
        self.assertEqual([i.restaurant_id for i in result], ["ok"])

    def test_cuisine_diversity_caps_top_slots(self):
        candidates = [candidate(f"x{i}", rating=4.9 - i * 0.01, cuisines=("biryani",)) for i in range(5)]
        candidates.append(candidate("other", rating=4.0, cuisines=("chinese",)))
        result = rank(candidates, Options(limit=4, max_per_cuisine=2))
        top_three = [i.restaurant_id for i in result[:3]]
        self.assertLessEqual(top_three.count("x0") + top_three.count("x1") + top_three.count("x2"), 2)
        self.assertIn("other", [i.restaurant_id for i in result])

    def test_deterministic_tie_break(self):
        a = candidate("b-rest")
        b = candidate("a-rest")
        result = rank([a, b], Options(limit=2))
        self.assertEqual([i.restaurant_id for i in result], ["a-rest", "b-rest"])

    def test_price_preference(self):
        cheap = candidate("cheap", avg_price=99)
        pricey = candidate("pricey", avg_price=900)
        low = rank([cheap, pricey], Options(limit=2, price_pref="low"))
        high = rank([cheap, pricey], Options(limit=2, price_pref="high"))
        self.assertEqual(low[0].restaurant_id, "cheap")
        self.assertEqual(high[0].restaurant_id, "pricey")

    def test_invalid_options(self):
        with self.assertRaises(ValueError):
            rank([candidate("a")], Options(radius_km=0))
        with self.assertRaises(ValueError):
            rank([candidate("a")], Options(limit=0))
        with self.assertRaises(ValueError):
            rank([candidate("a")], Options(price_pref="luxury"))

    def test_invalid_candidate(self):
        with self.assertRaises(ValueError):
            rank([candidate("bad", rating=6.0)])
        with self.assertRaises(ValueError):
            rank([candidate("bad", distance_km=-1.0)])

    def test_limit_is_respected(self):
        candidates = [candidate(f"r{i}", rating=4.0 + i * 0.01) for i in range(10)]
        result = rank(candidates, Options(limit=3))
        self.assertEqual(len(result), 3)


if __name__ == "__main__":
    unittest.main(verbosity=2)
