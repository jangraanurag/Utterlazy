"""Organic restaurant ranker — dependency-free core.

Business rules:
- Sponsored candidates are REJECTED, never silently mixed into organic rank.
- Relevance = Bayesian rating, distance decay, prep time, popularity, reliability.
- New restaurants get a small exploration boost (anti-starvation), never a penalty.
- Deterministic tie-break on restaurant_id.
"""

from __future__ import annotations

from dataclasses import dataclass, field
from math import log1p
from typing import Iterable

MODEL_VERSION = "organic-ranker-v1"

PRIOR_MEAN = 4.0
PRIOR_WEIGHT = 20.0
EXPLORATION_BOOST = 0.06
PAID_BOOST = 0.05

WEIGHTS = {
    "rating": 0.35,
    "distance": 0.20,
    "prep": 0.15,
    "popularity": 0.15,
    "reliability": 0.10,
    "price": 0.05,
}
assert abs(sum(WEIGHTS.values()) - 1.0) < 1e-9

PRICE_BANDS = {
    "low": lambda p: p < 150,
    "medium": lambda p: 150 <= p <= 400,
    "high": lambda p: p > 400,
}


@dataclass(frozen=True)
class Candidate:
    restaurant_id: str
    rating: float
    rating_count: int
    distance_km: float
    prep_time_minutes: float = 20.0
    order_count_7d: int = 0
    cancellation_rate: float = 0.0
    avg_price: float = 0.0
    cuisines: tuple[str, ...] = ()
    is_new: bool = False
    sponsored: bool = False
    is_open: bool = True


@dataclass(frozen=True)
class Options:
    radius_km: float = 10.0
    limit: int = 20
    price_pref: str = "any"
    max_per_cuisine: int = 2


@dataclass(frozen=True)
class RankedItem:
    restaurant_id: str
    score: float
    breakdown: dict[str, float] = field(default_factory=dict)
    sponsored: bool = False


def _clamp(value: float, low: float = 0.0, high: float = 1.0) -> float:
    return max(low, min(high, value))


def _validate(candidate: Candidate) -> None:
    if not candidate.restaurant_id:
        raise ValueError("restaurant_id is required")
    if not 0.0 <= candidate.rating <= 5.0:
        raise ValueError(f"rating out of range for {candidate.restaurant_id!r}")
    if candidate.rating_count < 0:
        raise ValueError(f"rating_count must be >= 0 for {candidate.restaurant_id!r}")
    if candidate.distance_km < 0:
        raise ValueError(f"distance_km must be >= 0 for {candidate.restaurant_id!r}")
    if candidate.prep_time_minutes < 0:
        raise ValueError(f"prep_time_minutes must be >= 0 for {candidate.restaurant_id!r}")
    if not 0.0 <= candidate.cancellation_rate <= 1.0:
        raise ValueError(f"cancellation_rate must be 0..1 for {candidate.restaurant_id!r}")


def _bayesian_rating(rating: float, count: int) -> float:
    return (rating * count + PRIOR_MEAN * PRIOR_WEIGHT) / (count + PRIOR_WEIGHT)


def score_candidate(candidate: Candidate, options: Options) -> RankedItem:
    _validate(candidate)

    bayes = _bayesian_rating(candidate.rating, candidate.rating_count)
    rating_score = _clamp(bayes / 5.0)
    distance_score = _clamp(1.0 - (candidate.distance_km / options.radius_km))
    prep_score = _clamp(1.0 / (1.0 + candidate.prep_time_minutes / 20.0))
    popularity_score = _clamp(log1p(candidate.order_count_7d) / log1p(100))
    reliability_score = _clamp(1.0 - min(candidate.cancellation_rate, 0.5) * 2.0)

    if options.price_pref == "any":
        price_score = 0.5
    elif options.price_pref in PRICE_BANDS:
        price_score = 1.0 if PRICE_BANDS[options.price_pref](candidate.avg_price) else 0.0
    else:
        raise ValueError(f"unsupported price_pref {options.price_pref!r}")

    exploration = EXPLORATION_BOOST if (candidate.is_new or candidate.rating_count < 20) else 0.0

    breakdown = {
        "rating": round(rating_score * WEIGHTS["rating"], 6),
        "distance": round(distance_score * WEIGHTS["distance"], 6),
        "prep": round(prep_score * WEIGHTS["prep"], 6),
        "popularity": round(popularity_score * WEIGHTS["popularity"], 6),
        "reliability": round(reliability_score * WEIGHTS["reliability"], 6),
        "price": round(price_score * WEIGHTS["price"], 6),
        "exploration": round(exploration, 6),
    }

    total = round(sum(breakdown.values()), 6)
    return RankedItem(candidate.restaurant_id, total, breakdown)


def rank(
    candidates: Iterable[Candidate],
    options: Options | None = None,
    scorer=None,
) -> list[RankedItem]:
    """Rank candidates.

    ``scorer`` lets a trained model replace the default policy score without
    duplicating eligibility, tie-break, and diversity logic. It must return
    ``(score, breakdown)``.
    """
    options = options or Options()

    if not 0 < options.radius_km <= 50:
        raise ValueError("radius_km must be in (0, 50]")
    if not 1 <= options.limit <= 100:
        raise ValueError("limit must be in [1, 100]")
    if not 0 <= options.max_per_cuisine <= 10:
        raise ValueError("max_per_cuisine must be in [0, 10]")
    if options.price_pref != "any" and options.price_pref not in PRICE_BANDS:
        raise ValueError(f"unsupported price_pref {options.price_pref!r}")

    eligible = [
        c for c in candidates
        if c.is_open and c.distance_km <= options.radius_km
    ]

    if scorer is None:
        scored = [(c, score_candidate(c, options)) for c in eligible]
    else:
        scored = [
            (c, RankedItem(c.restaurant_id, round(score, 6), breakdown))
            for c in eligible
            for score, breakdown in [scorer(c, options)]
        ]

    ranked = []
    for candidate, item in scored:
        score = item.score
        breakdown = dict(item.breakdown)

        # Paid partners get a small, disclosed preference; unpaid stay visible.
        if candidate.sponsored:
            score = round(score + PAID_BOOST, 6)
            breakdown["paid_boost"] = PAID_BOOST

        ranked.append(RankedItem(candidate.restaurant_id, score, breakdown, candidate.sponsored))

    ranked.sort(key=lambda item: (-item.score, item.restaurant_id))

    by_id = {c.restaurant_id: c for c in eligible}
    selected: list[RankedItem] = []
    cuisine_counts: dict[str, int] = {}

    for item in ranked:
        if len(selected) >= options.limit:
            break

        candidate = by_id[item.restaurant_id]
        primary = candidate.cuisines[0] if candidate.cuisines else None

        if primary and options.max_per_cuisine > 0:
            if cuisine_counts.get(primary, 0) >= options.max_per_cuisine:
                continue
            cuisine_counts[primary] = cuisine_counts.get(primary, 0) + 1

        selected.append(item)

    # Fill remaining slots with the best capped-out items so the page is full.
    if len(selected) < options.limit:
        chosen = {item.restaurant_id for item in selected}
        for item in ranked:
            if len(selected) >= options.limit:
                break
            if item.restaurant_id not in chosen:
                selected.append(item)

    return selected
