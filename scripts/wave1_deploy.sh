#!/usr/bin/env bash
#
# UtterLazy Wave 1 deploy — catalog + whatsapp + event backbone.
# Idempotent: safe to re-run. Run from the repo root on the deploy host.
#
#   SERVICE_TOKEN=... WA_APP_KEY=... ./scripts/wave1_deploy.sh
#
set -euo pipefail

COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
COMPOSE="docker compose -f ${COMPOSE_FILE}"
APP_DIR="${APP_DIR:-/root/utterlazy}"

cd "${APP_DIR}"

log() { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }
die() { printf '\n\033[1;31mFAIL: %s\033[0m\n' "$*" >&2; exit 1; }

require() {
  command -v "$1" >/dev/null 2>&1 || die "missing required binary: $1"
}

require docker
docker compose version >/dev/null 2>&1 || die "docker compose plugin required"

[ -f "${COMPOSE_FILE}" ] || die "${COMPOSE_FILE} not found in ${APP_DIR}"
[ -n "${SERVICE_TOKEN:-}" ] || die "SERVICE_TOKEN is required (shared catalog <-> whatsapp)"
[ -n "${WA_APP_KEY:-}" ] || die "WA_APP_KEY is required (whatsapp-service APP_KEY)"

log "1/8 Build images"
${COMPOSE} build catalog-service whatsapp-service outbox-publisher whatsapp-consumer

log "2/8 Start data + event backbone (mysql, redis, kafka x3)"
${COMPOSE} up -d mysql redis kafka-1 kafka-2 kafka-3

wait_healthy() {
  local svc="$1" tries="${2:-60}"
  for _ in $(seq 1 "${tries}"); do
    status="$(${COMPOSE} ps --format json "${svc}" 2>/dev/null | tr -d '\n' | sed -n 's/.*"Health":"\([a-z]*\)".*/\1/p')"
    if [ "${status}" = "healthy" ] || [ -z "${status}" ]; then return 0; fi
    sleep 2
  done
  die "service ${svc} did not become healthy"
}

wait_healthy mysql 90
wait_healthy redis 30
wait_healthy kafka-1 90

log "3/8 Run catalog migrations"
${COMPOSE} run --rm --no-deps catalog-service php artisan migrate --force

log "4/8 Run whatsapp migrations"
${COMPOSE} run --rm --no-deps whatsapp-service php artisan migrate --force

log "5/8 Start application services"
${COMPOSE} up -d catalog-service api-gateway outbox-publisher whatsapp-service whatsapp-consumer queue-worker scheduler reverb nginx

log "6/8 Clear stale caches"
${COMPOSE} exec -T catalog-service php artisan optimize:clear >/dev/null
${COMPOSE} exec -T whatsapp-service php artisan optimize:clear >/dev/null

log "7/8 Health checks"
for i in $(seq 1 30); do
  if curl -fsS http://127.0.0.1:8001/api/health >/dev/null 2>&1; then break; fi
  sleep 2
  [ "$i" = "30" ] && die "catalog health check failed"
done
curl -fsS http://127.0.0.1:8001/api/ready >/dev/null || die "catalog readiness failed"
curl -fsS http://127.0.0.1:8002/api/health >/dev/null || die "whatsapp health check failed"

log "8/8 Event bus smoke"
${COMPOSE} exec -T kafka-1 kafka-topics.sh --bootstrap-server kafka-1:9092 --list >/dev/null \
  || die "kafka not reachable"

OUTBOX_PENDING="$(${COMPOSE} exec -T mysql mysql -uroot -p"${DB_PASSWORD:-secret}" -N -B food_catalog \
  -e 'SELECT COUNT(*) FROM outbox WHERE published_at IS NULL;' 2>/dev/null | tail -1)"
printf 'outbox pending rows: %s\n' "${OUTBOX_PENDING}"

printf '\n\033[1;32mWave 1 deploy complete.\033[0m\n'
printf 'Next: run ./scripts/wave1_verify.sh with SERVICE_TOKEN + CITY_ID.\n'
printf 'Rollback: docker compose -f %s stop whatsapp-consumer whatsapp-service outbox-publisher; ' "${COMPOSE_FILE}"
printf 'catalog still serves orders (no behavior removed).\n'
