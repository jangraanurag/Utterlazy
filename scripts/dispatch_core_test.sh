#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../services/dispatch-core"
command -v javac >/dev/null 2>&1 || { echo "javac not found" >&2; exit 1; }
rm -rf out && mkdir -p out
javac --release 17 -d out $(find src/main/java src/test/java -name '*.java')
java -cp out com.utterlazy.dispatch.DispatchSelfTest
