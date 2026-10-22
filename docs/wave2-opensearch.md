# 🔎 Wave 2 — OpenSearch organic search

Fast organic restaurant search with a **MySQL fallback**. Disabled by default; paid placement is
never mixed in (ads stay in the ads auction).

## Surface

| Method | Path | Behaviour |
|---|---|---|
| GET | `/api/search/fast` | OpenSearch when enabled, MySQL search otherwise |
| CLI | `search:index-restaurants` | Bulk index active restaurants (daily 04:00; no-op when disabled) |

## Enable

```bash
docker compose -f docker-compose.prod.yml --profile search up -d opensearch
OPENSEARCH_ENABLED=true docker compose -f docker-compose.prod.yml up -d catalog-service scheduler
docker compose exec -T catalog-service php artisan search:index-restaurants
curl "http://127.0.0.1:8001/api/search/fast?q=biryani&limit=10"
```

Config: `OPENSEARCH_ENABLED`, `OPENSEARCH_URL`, `OPENSEARCH_INDEX`.

## Guarantees

- Search never hard-fails: disabled/unreachable/error → existing MySQL search response.
- Document shape (`RestaurantSearchDocument`): id, name, cuisine, rating, city, geo (`lat/lon`),
  is_active, is_accepting_orders.
- Indexing is a manual/daily bulk job; incremental sync can be added from catalog events later.
- The client is dependency-free raw HTTP (injectable transport in tests).

## Tests

7 checks in `tests/wave1_smoke.php`: search URL/body/hits, bulk NDJSON, failure → null,
document geo/open mapping.

## Not done

- incremental indexing from `catalog.*` events
- aggregations/facets and typo-tolerant suggestions beyond `multi_match`
- relevance tuning from click logs
