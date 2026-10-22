#!/usr/bin/env bash
#
# Compile and run the framework-free Java order-command core self-test.
# Requires a JDK 17+ (JDK 21/27 also work via --release 17).
#
set -euo pipefail

cd "$(dirname "$0")/../services/order-command-core"

command -v javac >/dev/null 2>&1 || { echo "javac not found (install JDK 17+)" >&2; exit 1; }

rm -rf out
mkdir -p out

# Compile only the dependency-free core (domain + application + ports) and its
# self-test. The Spring adapter is built and tested by Maven.
CORE_SOURCES=$(find src/main/java/com/utterlazy/order/domain \
                    src/main/java/com/utterlazy/order/application \
                    src/main/java/com/utterlazy/order/port \
                    -name '*.java')
javac --release 17 -d out ${CORE_SOURCES} src/test/java/com/utterlazy/order/OrderCoreSelfTest.java
java -cp out com.utterlazy.order.OrderCoreSelfTest
