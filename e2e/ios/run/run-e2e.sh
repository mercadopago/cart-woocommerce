#!/usr/bin/env bash
set -euo pipefail

canonical_port() {
  local name="$1"
  local raw="$2"
  local port
  case "$raw" in
    ''|*[!0-9]*)
      echo "[ios-e2e] $name deve ser uma porta numérica válida." >&2
      exit 1
      ;;
  esac
  port=$((10#$raw))
  if [ "$port" -lt 1 ] || [ "$port" -gt 65535 ]; then
    echo "[ios-e2e] $name deve estar entre 1 e 65535." >&2
    exit 1
  fi
  printf '%d' "$port"
}

SITE="${SITE:-mlb}"
SITE_LOWER="$(printf '%s' "$SITE" | tr '[:upper:]' '[:lower:]')"
if [ "$SITE_LOWER" != "mlb" ]; then
  echo "[ios-e2e] Neste incremento, SITE deve ser mlb." >&2
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
IOS_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
E2E_DIR="$(cd "$IOS_DIR/.." && pwd)"
REPO_DIR="$(cd "$E2E_DIR/.." && pwd)"
DOCKER_DIR="$REPO_DIR/docker-flexible-environment"
RUNTIME_DIR="$IOS_DIR/.runtime"
METADATA_FILE="$RUNTIME_DIR/simulator.json"
CA_FILE="$RUNTIME_DIR/caddy-root.crt"
APPIUM_LOG="$RUNTIME_DIR/appium.log"
IOS_HTTPS_PORT="$(canonical_port IOS_HTTPS_PORT "${IOS_HTTPS_PORT:-8443}")"
HTTP_PORT="$(canonical_port PORT "${PORT:-8080}")"
APPIUM_PORT="$(canonical_port APPIUM_PORT "${APPIUM_PORT:-4723}")"
IOS_STORE_URL="https://localhost:${IOS_HTTPS_PORT}"
LOCAL_HTTP_URL="http://localhost:${HTTP_PORT}"
EXECUTION_ID="${EXECUTION_ID:-$(node -e 'process.stdout.write(require("node:crypto").randomUUID())')}"
if [ "$EXECUTION_ID" = "." ] || [ "$EXECUTION_ID" = ".." ]; then
  echo "[ios-e2e] EXECUTION_ID inválido."
  exit 1
fi
IOS_UDID=""
APPIUM_PID=""
DOCKER_COMPOSE=()

mkdir -p "$RUNTIME_DIR" "$IOS_DIR/evidence"
chmod 700 "$RUNTIME_DIR"

cleanup() {
  local exit_code=$?
  if [ -n "$APPIUM_PID" ] && kill -0 "$APPIUM_PID" 2>/dev/null; then
    kill "$APPIUM_PID" 2>/dev/null || true
    wait "$APPIUM_PID" 2>/dev/null || true
  fi
  docker exec mp-wc-dev wp --allow-root option update siteurl "$LOCAL_HTTP_URL" >/dev/null 2>&1 || true
  docker exec mp-wc-dev wp --allow-root option update home "$LOCAL_HTTP_URL" >/dev/null 2>&1 || true
  (
    cd "$DOCKER_DIR"
    IOS_HTTPS_PORT="$IOS_HTTPS_PORT" "${DOCKER_COMPOSE[@]}" --profile ios stop ios-https >/dev/null 2>&1 || true
  )
  if [ -n "$IOS_UDID" ] && [ "${IOS_KEEP_RUNNING:-0}" != "1" ]; then
    node "$IOS_DIR/helpers/simulator.mjs" shutdown "$IOS_UDID" >/dev/null 2>&1 || true
  fi
  exit "$exit_code"
}
trap cleanup EXIT INT TERM

for command in docker node npm npx curl xcrun xcodebuild; do
  command -v "$command" >/dev/null 2>&1 || { echo "[ios-e2e] Comando ausente: $command" >&2; exit 1; }
done

if docker compose version >/dev/null 2>&1; then
  DOCKER_COMPOSE=(docker compose)
elif command -v docker-compose >/dev/null 2>&1; then
  DOCKER_COMPOSE=(docker-compose)
else
  echo "[ios-e2e] Docker Compose não encontrado." >&2
  exit 1
fi

node_version="$(node -p 'process.versions.node')"
node_major="${node_version%%.*}"
node_minor="$(printf '%s' "$node_version" | cut -d. -f2)"
if [ "$node_major" -lt 20 ] || { [ "$node_major" -eq 20 ] && [ "$node_minor" -lt 19 ]; }; then
  echo "[ios-e2e] Node 20.19+ é obrigatório; encontrado: $node_version" >&2
  exit 1
fi

echo "[ios-e2e] Preparando loja MLB e proxy HTTPS local..."
make -C "$DOCKER_DIR" up SITE=mlb PORT="$HTTP_PORT" >/dev/null
(
  cd "$DOCKER_DIR"
  IOS_HTTPS_PORT="$IOS_HTTPS_PORT" "${DOCKER_COMPOSE[@]}" --profile ios up -d ios-https >/dev/null
)

for _ in $(seq 1 60); do
  if docker exec mp-wc-ios-https test -f /data/caddy/pki/authorities/local/root.crt 2>/dev/null; then
    break
  fi
  sleep 0.5
done
docker cp mp-wc-ios-https:/data/caddy/pki/authorities/local/root.crt "$CA_FILE" >/dev/null
chmod 600 "$CA_FILE"

docker exec mp-wc-dev wp --allow-root option update siteurl "$IOS_STORE_URL" >/dev/null
docker exec mp-wc-dev wp --allow-root option update home "$IOS_STORE_URL" >/dev/null

for _ in $(seq 1 60); do
  if curl --silent --fail --cacert "$CA_FILE" "$IOS_STORE_URL/wp-json/" >/dev/null; then
    break
  fi
  sleep 0.5
done
curl --silent --fail --cacert "$CA_FILE" "$IOS_STORE_URL/wp-json/" >/dev/null \
  || { echo "[ios-e2e] HTTPS local não ficou disponível em $IOS_STORE_URL" >&2; exit 1; }

export IOS_SIMULATOR_METADATA="$METADATA_FILE"
export IOS_ERASE="${IOS_ERASE:-1}"
IOS_UDID="$(node "$IOS_DIR/helpers/simulator.mjs" ensure)"
export IOS_UDID
export IOS_DEVICE_NAME="$(node -p "require('$METADATA_FILE').deviceName")"
export IOS_PLATFORM_VERSION="$(node -p "require('$METADATA_FILE').platformVersion")"
node "$IOS_DIR/helpers/simulator.mjs" trust-ca "$IOS_UDID" "$CA_FILE"

echo "[ios-e2e] Iniciando Appium/XCUITest em 127.0.0.1:${APPIUM_PORT}..."
(
  cd "$E2E_DIR"
  umask 077
  exec env APPIUM_HOME="$E2E_DIR" npx appium \
    --address 127.0.0.1 \
    --port "$APPIUM_PORT" \
    --base-path / \
    --log "$APPIUM_LOG" \
    --log-level error \
    --log-timestamp \
    >/dev/null 2>&1
) &
APPIUM_PID=$!

for _ in $(seq 1 120); do
  if curl --silent --fail "http://127.0.0.1:${APPIUM_PORT}/status" >/dev/null; then
    break
  fi
  if ! kill -0 "$APPIUM_PID" 2>/dev/null; then
    echo "[ios-e2e] Appium encerrou durante o startup. Consulte $APPIUM_LOG" >&2
    exit 1
  fi
  sleep 0.5
done
curl --silent --fail "http://127.0.0.1:${APPIUM_PORT}/status" >/dev/null \
  || { echo "[ios-e2e] Appium não respondeu no prazo. Consulte $APPIUM_LOG" >&2; exit 1; }

export APPIUM_URL="http://127.0.0.1:${APPIUM_PORT}"
export IOS_STORE_URL
export EXECUTION_ID
export SITE=MLB
export PLAYWRIGHT_HTML_OPEN=never

PLAYWRIGHT_FILTER=(--grep "${IOS_TEST_GREP:-.}" --pass-with-no-tests)

echo "[ios-e2e] Executando Classic (parcelas + pagamentos aprovado e rejeitado)..."
(
  cd "$E2E_DIR"
  CHECKOUT=classic IOS_CHECKOUT=classic npx playwright test \
    --config ios/playwright.config.js \
    ios/tests/mlb/installments-sync-classic.spec.js \
    ios/tests/mlb/approved-credit-card-classic.spec.js \
    ios/tests/mlb/rejected-credit-card-classic.spec.js \
    "${PLAYWRIGHT_FILTER[@]}"
)

echo "[ios-e2e] Executando Blocks (parcelas + pagamento aprovado)..."
(
  cd "$E2E_DIR"
  CHECKOUT=blocks IOS_CHECKOUT=blocks npx playwright test \
    --config ios/playwright.config.js \
    ios/tests/mlb/installments-sync-blocks.spec.js \
    ios/tests/mlb/approved-credit-card-blocks.spec.js \
    "${PLAYWRIGHT_FILTER[@]}"
)

echo "[ios-e2e] Concluído. Evidências: $IOS_DIR/evidence/$EXECUTION_ID"
