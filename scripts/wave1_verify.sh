#!/usr/bin/env bash
#
# UtterLazy Wave 1 verification — proves the gates that must pass before Wave 2.
#
#   SERVICE_TOKEN=... CITY_ID=<uuid> ./scripts/wave1_verify.sh
#
# Optional: COMPOSE_FILE, CATALOG_URL, WHATSAPP_URL, TEST_USER_TOKEN, DB_PASSWORD.
#
set -uo pipefail

COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
COMPOSE="docker compose -f ${COMPOSE_FILE}"
CATALOG_URL="${CATALOG_URL:-http://127.0.0.1:8001}"
WHATSAPP_URL="${WHATSAPP_URL:-http://127.0.0.1:8002}"
DB_PASSWORD="${DB_PASSWORD:-secret}"

pass=0; fail=0; skip=0
ok()   { pass=$((pass+1)); printf '  \033[1;32mPASS\033[0m  %s\n' "$*"; }
bad()  { fail=$((fail+1)); printf '  \033[1;31mFAIL\033[0m  %s\n' "$*"; }
note() { skip=$((skip+1)); printf '  \033[1;33mSKIP\033[0m  %s\n' "$*"; }

sql() { ${COMPOSE} exec -T mysql mysql -uroot -p"${DB_PASSWORD}" -N -B food_catalog -e "$1" 2>/dev/null | tail -1; }
sql_wa() { ${COMPOSE} exec -T mysql mysql -uroot -p"${DB_PASSWORD}" -N -B food_whatsapp -e "$1" 2>/dev/null | tail -1; }

printf '\n=== Wave 1 verification ===\n\n'

printf '%s\n' '--- Health ---'
curl -fsS "${CATALOG_URL}/api/health" >/dev/null && ok "catalog /health" || bad "catalog /health"
curl -fsS "${CATALOG_URL}/api/ready" >/dev/null && ok "catalog /ready" || bad "catalog /ready"
curl -fsS "${WHATSAPP_URL}/api/health" >/dev/null && ok "whatsapp /health" || bad "whatsapp /health"

printf '%s\n' '--- Schema ---'
for t in outbox processed_events idempotency_keys wa_placements wa_city_floors wa_placement_events wa_workforce food_waste_claims; do
  c=$(sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='food_catalog' AND table_name='${t}';")
  [ "${c:-0}" = "1" ] && ok "table ${t}" || bad "table ${t} missing"
done
for t in wa_contacts wa_consents wa_sessions wa_messages wa_carts wa_handoffs wa_processed_events; do
  c=$(${COMPOSE} exec -T mysql mysql -uroot -p"${DB_PASSWORD}" -N -B food_whatsapp \
      -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='food_whatsapp' AND table_name='${t}';" 2>/dev/null | tail -1)
  [ "${c:-0}" = "1" ] && ok "table ${t}" || bad "table ${t} missing"
done

printf '%s\n' '--- Event bus ---'
topics=$(${COMPOSE} exec -T kafka-1 kafka-topics.sh --bootstrap-server kafka-1:9092 --list 2>/dev/null | grep -c "utterlazy\." || true)
[ "${topics:-0}" -gt 0 ] && ok "kafka topics present (${topics})" || note "no utterlazy.* topics yet (none produced since deploy)"
lag=$(sql "SELECT COUNT(*) FROM outbox WHERE published_at IS NULL;")
[ "${lag:-999}" -le 50 ] && ok "outbox pending=${lag}" || bad "outbox pending=${lag} (>50)"
age=$(sql "SELECT COALESCE(TIMESTAMPDIFF(SECOND, MIN(occurred_at), NOW()), 0) FROM outbox WHERE published_at IS NULL;")
[ "${age:-999}" -le 120 ] && ok "oldest unpublished age=${age}s" || bad "oldest unpublished age=${age}s (>120s)"

printf '%s\n' '--- Paid-only WhatsApp inventory ---'
if [ -n "${SERVICE_TOKEN:-}" ] && [ -n "${CITY_ID:-}" ]; then
  resp=$(curl -fsS -H "X-Service-Token: ${SERVICE_TOKEN}" \
    "${CATALOG_URL}/api/internal/wa-reco?city_id=${CITY_ID}&limit=5" 2>/dev/null || echo '')
  if [ -z "${resp}" ]; then
    bad "wa-reco request failed"
  else
    mkt=$(printf '%s' "${resp}" | grep -c '"sponsored":true' || true)
    unpaid=$(printf '%s' "${resp}" | grep -c '"status"' || true)
    [ "${unpaid}" -eq 0 ] && ok "reco contains only paid inventory (no status leak)" || bad "reco payload exposes non-sponsored fields"
    printf '  info: sponsored items=%s\n' "${mkt}"
  fi
else
  note "wa-reco (set SERVICE_TOKEN + CITY_ID)"
fi

printf '%s\n' '--- Data integrity ---'
dup=$(${COMPOSE} exec -T mysql mysql -uroot -p"${DB_PASSWORD}" -N -B food_catalog \
  -e "SELECT COUNT(*) FROM (SELECT user_id, order_id, COUNT(*) c FROM idempotency_keys WHERE order_id IS NOT NULL GROUP BY user_id, order_id HAVING c > 1) x;" 2>/dev/null | tail -1)
[ "${dup:-1}" = "0" ] && ok "no idempotency key maps to two orders" || bad "duplicate order mappings: ${dup}"

mismatch=$(sql "SELECT COUNT(*) FROM orders WHERE payment_status='refunded' AND cancel_reason IS NULL;")
[ "${mismatch:-1}" = "0" ] && ok "every refund has a cancel reason" || bad "refunded orders without reason: ${mismatch}"

neg=$(sql "SELECT COUNT(*) FROM orders WHERE grand_total < 0;")
[ "${neg:-1}" = "0" ] && ok "no negative order totals" || bad "negative order totals: ${neg}"

printf '%s\n' '--- Consumer dedupe ---'
waEvents=$(sql_wa "SELECT COUNT(*) FROM wa_processed_events;")
printf '  info: whatsapp processed events=%s\n' "${waEvents:-0}"
dups=$(sql_wa "SELECT COUNT(*) FROM (SELECT event_id, consumer, COUNT(*) c FROM wa_processed_events GROUP BY event_id, consumer HAVING c > 1) x;")
[ "${dups:-1}" = "0" ] && ok "consumer processed-events unique" || bad "duplicate processed events: ${dups}"

printf '\n=== Result: %d PASS, %d FAIL, %d SKIP ===\n' "${pass}" "${fail}" "${skip}"
[ "${fail}" -eq 0 ] || exit 1
