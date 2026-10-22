#!/usr/bin/env bash
#
# Build the Spring adapter and run its integration tests (H2 + MockMvc).
# Requires JDK 17+ and Maven on PATH, or MVN=/path/to/mvn.
#
set -euo pipefail

cd "$(dirname "$0")/../services/order-command-core"

MVN="${MVN:-mvn}"
if ! command -v "${MVN}" >/dev/null 2>&1; then
  echo "Maven not found. Set MVN=/path/to/mvn (or install Maven 3.9+)." >&2
  echo "Dependency-free core still runs with: ../scripts/order_core_test.sh" >&2
  exit 1
fi

"${MVN}" -B test
