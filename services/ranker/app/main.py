"""HTTP wrapper for the organic ranker.

The ranking logic lives in `ranking.py` and has no third-party dependency;
FastAPI is only the transport.
"""

from __future__ import annotations

import logging
import os

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field

from .model import (
    ModelError,
    bucket_for,
    load_model,
    model_scorer,
    select_mode,
)
from .ranking import (
    MODEL_VERSION,
    Candidate,
    Options,
    rank,
)

logger = logging.getLogger("ranker")

MODEL_ENABLED = os.getenv("RANKER_MODEL_ENABLED", "false").lower() == "true"
SHADOW_ENABLED = os.getenv("RANKER_SHADOW_ENABLED", "false").lower() == "true"
MODEL_SHARE_PERCENT = max(0, min(100, int(os.getenv("RANKER_MODEL_SHARE_PERCENT", "10"))))
MODEL_PATH = os.getenv("RANKER_MODEL_PATH", "model.json")

MODEL = None
MODEL_ERROR = None
if MODEL_ENABLED or SHADOW_ENABLED:
    try:
        MODEL = load_model(MODEL_PATH)
        logger.info("Loaded ranker model %s", MODEL.version)
    except (ModelError, OSError, ValueError) as exc:
        MODEL_ERROR = str(exc)
        logger.warning("Ranker model unavailable, policy only: %s", exc)

app = FastAPI(title="UtterLazy organic ranker", version=MODEL_VERSION)


class CandidateIn(BaseModel):
    restaurant_id: str = Field(min_length=1, max_length=64)
    rating: float = Field(ge=0.0, le=5.0)
    rating_count: int = Field(ge=0)
    distance_km: float = Field(ge=0.0)
    prep_time_minutes: float = Field(default=20.0, ge=0.0)
    order_count_7d: int = Field(default=0, ge=0)
    cancellation_rate: float = Field(default=0.0, ge=0.0, le=1.0)
    avg_price: float = Field(default=0.0, ge=0.0)
    cuisines: list[str] = Field(default_factory=list)
    is_new: bool = False
    sponsored: bool = False
    is_open: bool = True


class RankOptionsIn(BaseModel):
    radius_km: float = Field(default=10.0, gt=0.0, le=50.0)
    limit: int = Field(default=20, ge=1, le=100)
    price_pref: str = "any"
    max_per_cuisine: int = Field(default=2, ge=0, le=10)
    assignment_key: str | None = Field(default=None, max_length=120)


class RankRequest(BaseModel):
    candidates: list[CandidateIn] = Field(min_length=1, max_length=500)
    options: RankOptionsIn = Field(default_factory=RankOptionsIn)


class RankedItemOut(BaseModel):
    restaurant_id: str
    score: float
    breakdown: dict[str, float]
    sponsored: bool = False


class RankResponse(BaseModel):
    model_version: str
    scoring: str
    experiment: dict | None = None
    shadow: dict | None = None
    items: list[RankedItemOut]


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok", "service": "ranker", "model_version": MODEL_VERSION}


@app.post("/rank", response_model=RankResponse)
def rank_candidates(request: RankRequest) -> RankResponse:
    candidates = [
        Candidate(
            restaurant_id=c.restaurant_id,
            rating=c.rating,
            rating_count=c.rating_count,
            distance_km=c.distance_km,
            prep_time_minutes=c.prep_time_minutes,
            order_count_7d=c.order_count_7d,
            cancellation_rate=c.cancellation_rate,
            avg_price=c.avg_price,
            cuisines=tuple(c.cuisines),
            is_new=c.is_new,
            sponsored=c.sponsored,
            is_open=c.is_open,
        )
        for c in request.candidates
    ]

    options = Options(
        radius_km=request.options.radius_km,
        limit=request.options.limit,
        price_pref=request.options.price_pref,
        max_per_cuisine=request.options.max_per_cuisine,
    )

    try:
        policy_items = rank(candidates, options)
    except ValueError as exc:
        # Includes sponsored candidates — they must never enter organic rank.
        raise HTTPException(status_code=422, detail=str(exc)) from exc

    assignment_key = request.options.assignment_key
    bucket = bucket_for(assignment_key) if assignment_key else None
    mode = select_mode(
        model_enabled=MODEL_ENABLED,
        shadow_enabled=SHADOW_ENABLED,
        model_available=MODEL is not None,
        bucket=bucket,
        share_percent=MODEL_SHARE_PERCENT,
    )

    experiment = None
    if MODEL_ENABLED or SHADOW_ENABLED:
        experiment = {
            "model_enabled": MODEL_ENABLED,
            "shadow_enabled": SHADOW_ENABLED,
            "share_percent": MODEL_SHARE_PERCENT,
            "bucket": bucket,
            "model_available": MODEL is not None,
            "model_version": MODEL.version if MODEL else None,
        }

    chosen = policy_items
    shadow = None

    if mode == "model" and MODEL is not None:
        chosen = rank(candidates, options, scorer=model_scorer(MODEL))
    elif mode == "shadow_policy" and MODEL is not None:
        shadow_items = rank(candidates, options, scorer=model_scorer(MODEL))
        shadow = {
            "model_version": MODEL.version,
            "items": [
                {"restaurant_id": item.restaurant_id, "score": item.score}
                for item in shadow_items
            ],
        }

    response_version = MODEL.version if mode == "model" and MODEL else MODEL_VERSION

    return RankResponse(
        model_version=response_version,
        scoring=mode,
        experiment=experiment,
        shadow=shadow,
        items=[
            RankedItemOut(
                restaurant_id=item.restaurant_id,
                score=item.score,
                breakdown=item.breakdown,
                sponsored=item.sponsored,
            )
            for item in chosen
        ],
    )
