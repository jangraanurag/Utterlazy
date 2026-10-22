#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../services/dispatch-core"
command -v javac >/dev/null 2>&1 || { echo "javac not found" >&2; exit 1; }
rm -rf out && mkdir -p out
# Compile only the dependency-free policy core; the Spring tier is tested by Maven.
javac --release 17 -d out \
  $(find src/main/java/com/utterlazy/dispatch -maxdepth 1 -name '*.java' ! -name 'DispatchApplication.java') \
  src/test/java/com/utterlazy/dispatch/DispatchSelfTest.java
java -cp out com.utterlazy.dispatch.DispatchSelfTest
