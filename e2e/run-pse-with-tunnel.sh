#!/usr/bin/env bash
# Run the MCO PSE E2E tests, which REQUIRE a public callback_url.
#
# Why: PseTransaction sets callback_url = order-received URL (= the WC home URL). In the
# local E2E the home URL is http://localhost:8080, and the MP API rejects it with
# "callback_url attribute must be url valid" (localhost is not accepted). The notification_url
# is rewritten via _mp_custom_domain, but callback_url uses the raw order URL — so PSE cannot
# be created on plain localhost. PSE also depends on MP's BankTransfers service, which can
# return a transient "BankTransfers Timeout" (absorbed by Playwright retries).
#
# This script spins up a temporary public domain (cloudflared), points WordPress home/siteurl
# at it so the callback_url is valid, runs the PSE tests, then restores localhost and tears
# the tunnel down. The store must already be on MCO (run any MCO test first, or this script
# warms it up). PSE is the ONLY flow that needs this — all other tests run fine on localhost.
#
# Usage: bash e2e/run-pse-with-tunnel.sh
set -u

E2E="$(cd "$(dirname "$0")" && pwd)"
CONTAINER=mp-wc-dev
PORT=8080
WP="docker exec $CONTAINER wp --allow-root"
CF_LOG=/tmp/pse_cf.log

cleanup() {
  echo "[pse] restoring localhost + stopping tunnel..."
  $WP option update home "http://localhost:$PORT" >/dev/null 2>&1 || true
  $WP option update siteurl "http://localhost:$PORT" >/dev/null 2>&1 || true
  pkill -f 'cloudflared tunnel' 2>/dev/null || true
  rm -f "$CF_LOG"
}
trap cleanup EXIT

# 1) Warm up: ensure the store is configured for MCO (test mode) so the real run below
#    does not trigger a store reset that would wipe the tunnel home URL.
echo "[pse] warming up MCO config..."
( cd "$E2E" && SITE=MCO CHECKOUT=classic npx playwright test tests/mco/pse/pse_payment.spec.js --workers=1 --retries=0 --reporter=dot >/dev/null 2>&1 ) || true

# 2) Start cloudflared tunnel and capture the public URL.
echo "[pse] starting cloudflared tunnel..."
cloudflared tunnel --url "http://localhost:$PORT" > "$CF_LOG" 2>&1 &
URL=""
for i in $(seq 1 30); do
  URL=$(grep -oE 'https://[a-z0-9-]+\.trycloudflare\.com' "$CF_LOG" | head -1)
  [ -n "$URL" ] && break
  sleep 1
done
if [ -z "$URL" ]; then echo "[pse] ERROR: could not get tunnel URL"; exit 1; fi
echo "[pse] tunnel: $URL"

# 3) Point WordPress at the tunnel so callback_url (order-received) is a valid public URL.
$WP option update home "$URL" >/dev/null 2>&1
$WP option update siteurl "$URL" >/dev/null 2>&1

# 4) Run PSE (classic + blocks) against the tunnel. retries=2 (config) absorbs BankTransfers Timeout.
rc=0
for mode in classic blocks; do
  echo "[pse] === MCO PSE $mode ==="
  ( cd "$E2E" && SITE=MCO CHECKOUT="$mode" SHOP_URL="$URL/shop" \
      npx playwright test tests/mco/pse/pse_payment.spec.js --workers=1 --reporter=line ) || rc=1
done

echo "[pse] done (rc=$rc)"
exit $rc
