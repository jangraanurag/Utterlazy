"""Trained-model loading, validation, scoring, and A/B gate helpers.

The serving adapter only uses a model when:
- the feature list matches exactly,
- test metrics clear the minimum gate,
- the assignment bucket falls inside the configured share.

Anything else falls back to the deterministic weighted policy.
"""

from __future__ import annotations

import hashlib
import json
import math
from dataclasses import dataclass
from pathlib import Path

from .ranking import PRIOR_MEAN, PRIOR_WEIGHT, Candidate, Options

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

MIN_TEST_AUC = 0.60
MIN_TEST_ACCURACY = 0.55


class ModelError(ValueError):
    pass


@dataclass(frozen=True)
class TrainedModel:
    version: str
    features: tuple[str, ...]
    weights: tuple[float, ...]
    bias: float
    means: dict[str, float]
    stds: dict[str, float]
    metrics: dict

    def score(self, feature_values: dict[str, float]) -> float:
        z = self.bias
        for weight, feature in zip(self.weights, self.features):
            mean = self.means[feature]
            std = self.stds[feature] or 1.0
            z += weight * ((feature_values[feature] - mean) / std)

        if z >= 0:
            return 1.0 / (1.0 + math.exp(-z))
        exp = math.exp(z)
        return exp / (1.0 + exp)


def load_model(path: str | Path) -> TrainedModel:
    try:
        with open(path, encoding="utf-8") as handle:
            data = json.load(handle)
    except FileNotFoundError as exc:
        raise ModelError(f"model not found: {path}") from exc
    except json.JSONDecodeError as exc:
        raise ModelError("model is not valid JSON") from exc

    if not isinstance(data, dict):
        raise ModelError("model must be an object")

    features = data.get("features")
    if features != FEATURES:
        raise ModelError("model feature list does not match the serving contract")

    weights = data.get("weights")
    if not isinstance(weights, list) or len(weights) != len(FEATURES):
        raise ModelError("model weights are missing or mis-sized")

    means = data.get("means") or {}
    stds = data.get("stds") or {}
    for feature in FEATURES:
        if feature not in means or feature not in stds:
            raise ModelError(f"model scaling missing for {feature}")
        if not stds[feature]:
            raise ModelError(f"model std is zero for {feature}")

    metrics = data.get("metrics") or {}
    test = metrics.get("test") or {}
    if float(test.get("auc", 0)) < MIN_TEST_AUC:
        raise ModelError(f"model AUC below serving gate ({MIN_TEST_AUC})")

    if float(test.get("accuracy", 0)) < MIN_TEST_ACCURACY:
        raise ModelError(f"model accuracy below serving gate ({MIN_TEST_ACCURACY})")

    return TrainedModel(
        version=str(data.get("version", "unknown")),
        features=tuple(FEATURES),
        weights=tuple(float(w) for w in weights),
        bias=float(data.get("bias", 0.0)),
        means={f: float(means[f]) for f in FEATURES},
        stds={f: float(stds[f]) for f in FEATURES},
        metrics=metrics,
    )


def bayes_rating(rating: float, rating_count: int) -> float:
    return (rating * rating_count + PRIOR_MEAN * PRIOR_WEIGHT) / (rating_count + PRIOR_WEIGHT)


def candidate_features(candidate: Candidate) -> dict[str, float]:
    return {
        "bayes_rating": bayes_rating(candidate.rating, candidate.rating_count),
        "rating_count": float(candidate.rating_count),
        "distance_km": float(candidate.distance_km),
        "prep_time_minutes": float(candidate.prep_time_minutes),
        "order_count_7d": float(candidate.order_count_7d),
        "cancellation_rate": float(candidate.cancellation_rate),
        "avg_price": float(candidate.avg_price),
        "is_new": 1.0 if (candidate.is_new or candidate.rating_count < 20) else 0.0,
    }


def model_scorer(model: TrainedModel):
    def score(candidate: Candidate, options: Options):
        value = model.score(candidate_features(candidate))
        return value, {"model": round(value, 6)}

    return score


def bucket_for(assignment_key: str, buckets: int = 100) -> int:
    digest = hashlib.sha256(f"ranker-v1|{assignment_key}".encode("utf-8")).digest()
    return int.from_bytes(digest[:4], "big") % buckets


def select_mode(model_enabled: bool, shadow_enabled: bool, model_available: bool,
                bucket: int | None, share_percent: int) -> str:
    """Return model | shadow_policy | policy | policy_fallback."""
    if model_enabled and not model_available:
        return "policy_fallback"

    if model_enabled and model_available and bucket is not None and bucket < share_percent:
        return "model"

    if shadow_enabled and model_available:
        return "shadow_policy"

    return "policy"
