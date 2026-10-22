#!/usr/bin/env bash
#
# UtterLazy certification — proves every automated gate in one run.
#
#   MODE=offline ./scripts/certify.sh                 # local/CI gates
#   MODE=staging SERVICE_TOKEN=... CITY_ID=... COMPOSE_FILE=docker-compose.prod.yml \
#     ./scripts/certify.sh                            # staging gates (docker compose on host)
#
# Optional:
#   WITH_FRONTENDS=true   build admin/vendor web
#   MVN=/path/to/mvn      Maven binary for the Spring adapter tests
#   RANKER_DEPS=/path     PYTHONPATH for FastAPI API tests
#
# Writes a Markdown + JSON report to infra/certification/.
# Exit code: 0 if no gate failed (skips are allowed), 1 otherwise.
#
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "${ROOT}"

MODE="${MODE:-offline}"
WITH_FRONTENDS="${WITH_FRONTENDS:-false}"
OUT_DIR="${OUT_DIR:-infra/certification}"
TS="$(date -u +%Y%m%dT%H%M%SZ)"
REPORT="${OUT_DIR}/report-${TS}.md"
JSON="${OUT_DIR}/report-${TS}.json"
GIT="$(git rev-parse --short HEAD 2>/dev/null || echo 'n/a')"

mkdir -p "${OUT_DIR}"

results=()
record() { results+=("$1|$2|$3"); }

run_gate() {
  local name="$1"; shift
  local tmp status detail
  tmp="$(mktemp)"
  if "$@" >"${tmp}" 2>&1; then
    record "${name}" PASS "ok"
  else
    status=FAIL
    detail="$(tail -n 3 "${tmp}" | tr '\n' ' ' | cut -c1-280)"
    record "${name}" "${status}" "${detail}"
  fi
  rm -f "${tmp}"
}

skip_gate() { record "$1" SKIP "$2"; }

printf '\n=== UtterLazy certification (%s) ===\n\n' "${MODE}"

# ─── Static / offline gates ──────────────────────────────────────────────────
run_gate "PHP wave1 smoke (313 checks)" bash -c 'cd catalog-service && php tests/wave1_smoke.php'
run_gate "WhatsApp helper tests" bash -c 'cd whatsapp-service && php tests/phone_normalizer_test.php'
run_gate "WhatsApp boot + routes" bash -c 'cd whatsapp-service && php artisan route:list > /dev/null'
run_gate "Pricing parity (PHP → Java)" ./scripts/pricing_parity.sh
run_gate "Order-command core self-test" ./scripts/order_core_test.sh
run_gate "Dispatch core self-test" ./scripts/dispatch_core_test.sh

MVN_BIN="${MVN:-}"
if [ -z "${MVN_BIN}" ] && command -v mvn >/dev/null 2>&1; then MVN_BIN="$(command -v mvn)"; fi
if [ -z "${MVN_BIN}" ] && [ -x /tmp/opencode/tools/apache-maven-3.9.9/bin/mvn ]; then
  MVN_BIN=/tmp/opencode/tools/apache-maven-3.9.9/bin/mvn
fi

if [ -n "${MVN_BIN}" ]; then
  run_gate "Spring adapter tests (MockMvc + H2)" bash -c "\"${MVN_BIN}\" -q -B -f services/order-command-core/pom.xml test"
  run_gate "Dispatch service tests (MockMvc)" bash -c "\"${MVN_BIN}\" -q -B -f services/dispatch-core/pom.xml test"
else
  skip_gate "Spring adapter tests" "Maven not found (set MVN=...)"
fi

RANKER_ENV=()
if [ -n "${RANKER_DEPS:-}" ]; then RANKER_ENV=(env "PYTHONPATH=${RANKER_DEPS}"); fi
if command -v python3 >/dev/null 2>&1; then
  if [ ${#RANKER_ENV[@]} -gt 0 ]; then
    run_gate "Ranker tests (core + API + trainer)" bash -c "cd services/ranker && PYTHONPATH='${RANKER_DEPS}' python3 -m unittest discover -s tests"
  else
    run_gate "Ranker tests (core + trainer; API skipped)" bash -c 'cd services/ranker && python3 -m unittest discover -s tests'
  fi
else
  skip_gate "Ranker tests" "python3 not found"
fi

if command -v python3 >/dev/null 2>&1; then
  run_gate "Compose + CI YAML" python3 -c "import yaml; [yaml.safe_load(open(f)) for f in ('docker-compose.yml','docker-compose.prod.yml','.github/workflows/wave1-gates.yml')]"
else
  skip_gate "Compose + CI YAML" "python3 not found"
fi

if command -v docker >/dev/null 2>&1; then
  run_gate "Compose config (dev)" docker compose -f docker-compose.yml config --quiet
  run_gate "Compose config (prod)" bash -c 'SERVICE_TOKEN=ci WA_APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= docker compose -f docker-compose.prod.yml config --quiet'
else
  skip_gate "Compose config" "docker not available"
fi

DOC_COUNT="$(find docs -maxdepth 1 -name '*.md' | wc -l | tr -d ' ')"
if [ "${DOC_COUNT}" -ge 12 ]; then
  record "Docs present (architecture/hld/lld/runbook/waves)" PASS "${DOC_COUNT} docs"
else
  record "Docs present" FAIL "expected >=12 docs, found ${DOC_COUNT}"
fi

if [ "${WITH_FRONTENDS}" = "true" ]; then
  run_gate "Admin web build" bash -c 'cd web/admin-web && npm run build'
  run_gate "Vendor web build" bash -c 'cd web/vendor-web && npm run build'
else
  skip_gate "Frontend builds" "WITH_FRONTENDS=false"
fi

# ─── Staging gates ───────────────────────────────────────────────────────────
if [ "${MODE}" = "staging" ]; then
  : "${SERVICE_TOKEN:?SERVICE_TOKEN required for staging}"
  : "${CITY_ID:?CITY_ID required for staging}"
  : "${COMPOSE_FILE:?COMPOSE_FILE required for staging}"

  run_gate "Wave 1 staging verification" env SERVICE_TOKEN="${SERVICE_TOKEN}" CITY_ID="${CITY_ID}" COMPOSE_FILE="${COMPOSE_FILE}" ./scripts/wave1_verify.sh

  COMPOSE=(docker compose -f "${COMPOSE_FILE}")

  run_gate "Pricing parity report (24h, fails on mismatch)" \
    "${COMPOSE[@]}" exec -T catalog-service php artisan pricing:parity-report --hours=24

  if [ "${ORDER_COMMAND_SHADOW_ENABLED:-false}" = "true" ]; then
    run_gate "Shadow order parity (24h, fails on mismatch)" \
      "${COMPOSE[@]}" exec -T catalog-service php artisan order-command:compare --hours=24 --fail-on-mismatch
  else
    skip_gate "Shadow order parity" "ORDER_COMMAND_SHADOW_ENABLED not true"
  fi

  if [ -n "${RANKER_URL:-}" ]; then
    run_gate "Ranker health" curl -fsS "${RANKER_URL}/health"
  else
    skip_gate "Ranker health" "RANKER_URL not set"
  fi
else
  skip_gate "Staging gates" "MODE=offline"
fi

# ─── Report ──────────────────────────────────────────────────────────────────
pass=0; fail=0; skip=0
for row in "${results[@]}"; do
  IFS='|' read -r _ status _ <<< "${row}"
  case "${status}" in
    PASS) pass=$((pass+1)) ;;
    FAIL) fail=$((fail+1)) ;;
    *) skip=$((skip+1)) ;;
  esac
done

{
  printf '# UtterLazy certification — %s\n\n' "${TS}"
  printf -- '- Mode: `%s`\n- Commit: `%s`\n- Result: **%d PASS / %d FAIL / %d SKIP**\n\n' "${MODE}" "${GIT}" "${pass}" "${fail}" "${skip}"
  printf '| Gate | Status | Detail |\n|---|---|---|\n'
  for row in "${results[@]}"; do
    IFS='|' read -r name status detail <<< "${row}"
    printf '| %s | %s | %s |\n' "${name}" "${status}" "${detail//$'\n'/ }"
  done
  printf '\n> SKIP is not a pass. Resolve every skip before declaring production readiness.\n'
} > "${REPORT}"

python3 - "$REPORT" "$JSON" "${results[@]}" <<'PY'
import json, sys
report, out, rows = sys.argv[1], sys.argv[2], sys.argv[3:]
entries = []
for row in rows:
    name, status, detail = row.split('|', 2)
    entries.append({"gate": name, "status": status, "detail": detail})
with open(out, 'w') as handle:
    json.dump({"report": report, "gates": entries}, handle, indent=2)
    handle.write("\n")
PY

cat "${REPORT}"
printf '\nReport: %s\nJSON:   %s\n' "${REPORT}" "${JSON}"

[ "${fail}" -eq 0 ]
