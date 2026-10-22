#!/usr/bin/env bash
#
# Cross-language pricing parity gate.
#
# 1. Generate canonical vectors from the Laravel pricing mirror.
# 2. Fail if the committed golden file drifted.
# 3. Recompute every vector in Java and compare persisted money fields.
#
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "${ROOT}"

PHP_BIN="${PHP_BIN:-php}"
JAVAC_BIN="${JAVAC_BIN:-javac}"
JAVA_BIN="${JAVA_BIN:-java}"

GOLDEN="infra/pricing/golden.tsv"
GENERATED="$(mktemp)"
BUILD="$(mktemp -d)"
trap 'rm -rf "${GENERATED}" "${BUILD}"' EXIT

[ -f "${GOLDEN}" ] || { echo "missing ${GOLDEN}" >&2; exit 1; }

if [ ! -f catalog-service/.env ]; then
  cp catalog-service/.env.example catalog-service/.env
  (cd catalog-service && "${PHP_BIN}" artisan key:generate --force >/dev/null)
fi

(
  cd catalog-service
  "${PHP_BIN}" artisan pricing:vectors --write="${GENERATED}" >/dev/null
)

if ! diff -u "${GOLDEN}" "${GENERATED}"; then
  echo >&2
  echo "Golden drift detected. Regenerate with:" >&2
  echo "  cd catalog-service && php artisan pricing:vectors --write=../infra/pricing/golden.tsv" >&2
  exit 1
fi

mapfile -t SOURCES < <(find \
  services/order-command-core/src/main/java/com/utterlazy/order/domain \
  services/order-command-core/src/main/java/com/utterlazy/order/application \
  services/order-command-core/src/main/java/com/utterlazy/order/port \
  -name '*.java')
SOURCES+=("services/order-command-core/src/test/java/com/utterlazy/order/PricingParityCli.java")

"${JAVAC_BIN}" --release 17 -d "${BUILD}" "${SOURCES[@]}"
"${JAVA_BIN}" -cp "${BUILD}" com.utterlazy.order.PricingParityCli "${GOLDEN}"
