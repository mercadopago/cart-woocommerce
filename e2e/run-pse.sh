#!/usr/bin/env bash
# Run the MCO PSE E2E tests, which REQUIRE a public callback_url.
#
# Why: PseTransaction sets callback_url = order-received URL (= home_url()). On plain
# localhost the MP API rejects it ("callback_url attribute must be url valid"). Unlike
# notification_url, callback_url does not apply _mp_custom_domain. PSE also depends on
# MP's BankTransfers service, which can return a transient "BankTransfers Timeout"
# (absorbed by retries).
#
# Strategy (no external tunnel required):
#   A temporary WordPress mu-plugin hooks into woocommerce_get_checkout_order_received_url
#   — the exact filter in WC_Order::get_checkout_order_received_url() — which fires ONLY
#   when WC builds the order-received URL.
#   It replaces localhost with a valid HTTPS domain (https://e2e-test.example.com) so MP
#   accepts the callback_url. MP validates the URL format — the domain does NOT need to
#   be reachable. Unlike filtering home_url() globally, this filter never affects
#   WordPress canonical redirects, so Playwright navigation remains intact for all tests.
#
#   When running the full MCO suite (E2E_MCO_FULL=1), the run is split into two phases:
#     Phase 1 — non-PSE tests (chocustom/chopro/ticket): mu-plugin is NOT installed.
#     Phase 2 — PSE spec only: mu-plugin installed → PSE runs → mu-plugin removed.
#
# Usage: bash e2e/run-pse.sh
set -u

E2E="$(cd "$(dirname "$0")" && pwd)"
CONTAINER=mp-wc-dev
PORT=8080
# Public-looking domain for callback_url. MP validates format only — not reachability.
PSE_CALLBACK_DOMAIN="https://e2e-test.example.com"
MU_PLUGIN_PATH="/var/www/html/wp-content/mu-plugins/e2e-pse-callback.php"

# shellcheck disable=SC2329  # invoked via trap EXIT, not directly
cleanup() {
  echo "[pse] removing mu-plugin..."
  docker exec "$CONTAINER" rm -f "$MU_PLUGIN_PATH" 2>/dev/null || true
}
trap cleanup EXIT

install_mu_plugin() {
  local url="$1"
  echo "[pse] installing mu-plugin: woocommerce_get_checkout_order_received_url → $url"
  docker exec "$CONTAINER" bash -c "mkdir -p /var/www/html/wp-content/mu-plugins && cat > $MU_PLUGIN_PATH << 'EPHP'
<?php
// E2E PSE only: override the WooCommerce order-received URL so callback_url passes
// MP URL validation (format only — domain does NOT need to be reachable).
//
// Uses the WooCommerce-specific filter woocommerce_get_checkout_order_received_url
// (the exact name used in WC_Order::get_checkout_order_received_url()) instead of
// the generic home_url(): it only fires when WC builds the order-received URL, so
// it never affects WordPress canonical redirects or other home_url() calls —
// preventing the navigation breakage seen with the home_url() approach.
//
// Removed automatically by run-pse.sh cleanup.
add_filter('woocommerce_get_checkout_order_received_url', function(\$url) {
    return str_replace('http://localhost:${PORT}', '${url}', \$url);
});
EPHP
" 2>&1
  # Enable the PSE spec (it skips unless E2E_PSE_ENABLED=1). Set here, coupled to the
  # mu-plugin: the spec only makes sense when the callback_url override is in place, so
  # the same call that makes the URL valid also flags PSE as runnable. Never set in the
  # non-PSE phase, which runs before install_mu_plugin.
  export E2E_PSE_ENABLED=1
}

# ── Common Playwright args ────────────────────────────────────────────────────
_pse_reporter="line"
[ -n "${E2E_BLOB_DEST_DIR:-}" ] && _pse_reporter="blob,line"
[ -n "${E2E_JSON_OUTPUT_DIR:-}" ] && _pse_reporter="json,${_pse_reporter}"
read -r -a _pse_modes <<< "${E2E_PSE_MODES:-classic blocks}"
_retries_arg=()
[ -n "${E2E_PSE_RETRIES:-}" ] && _retries_arg=(--retries="$E2E_PSE_RETRIES")

# run_playwright <mode> <json-tag> <path...> — paths passed as separate args (Playwright
# treats a single space-joined string as one filter matching nothing → "No tests found").
# <json-tag> names the per-phase JSON/blob (MCO-<tag>.json) so the two phases of a full-suite
# run don't overwrite each other; they're merged back into MCO-<mode>.json afterwards.
run_playwright() {
  local mode="$1" tag="$2"; shift 2
  local paths=("$@")
  (
    cd "$E2E" || exit 1
    [ -n "${E2E_JSON_OUTPUT_DIR:-}" ] && \
      export PLAYWRIGHT_JSON_OUTPUT_NAME="${E2E_JSON_OUTPUT_DIR}/MCO-${tag}.json"
    SITE=MCO CHECKOUT="$mode" SHOP_URL="http://localhost:$PORT/shop" \
      npx playwright test "${paths[@]}" --workers=1 --no-deps \
      ${_retries_arg[@]+"${_retries_arg[@]}"} --reporter="$_pse_reporter"
  )
}

# move_blob <tag> — moves the run's blob to a unique name so merge-reports keeps every phase.
move_blob() {
  local tag="$1"
  if [ -n "${E2E_BLOB_DEST_DIR:-}" ] && [ -d "$E2E/blob-report" ]; then
    mkdir -p "$E2E_BLOB_DEST_DIR"
    find "$E2E/blob-report" -name "*.zip" \
      -exec mv {} "$E2E_BLOB_DEST_DIR/MCO-${tag}.zip" \;
  fi
}

# merge_json <mode> — combine the non-PSE and PSE phase JSONs into the single MCO-<mode>.json
# that run-all-report.sh parses (combine .suites, sum .stats). Removes the per-phase files.
merge_json() {
  local mode="$1"
  [ -z "${E2E_JSON_OUTPUT_DIR:-}" ] && return 0
  local main="${E2E_JSON_OUTPUT_DIR}/MCO-${mode}-nonpse.json"
  local pse="${E2E_JSON_OUTPUT_DIR}/MCO-${mode}-pse.json"
  local out="${E2E_JSON_OUTPUT_DIR}/MCO-${mode}.json"
  # If either phase produced no JSON, fall back to whichever exists.
  if [ ! -f "$main" ]; then [ -f "$pse" ] && mv "$pse" "$out"; return 0; fi
  if [ ! -f "$pse" ]; then mv "$main" "$out"; return 0; fi
  jq -s '{
    config: .[0].config,
    suites: ((.[0].suites // []) + (.[1].suites // [])),
    errors: ((.[0].errors // []) + (.[1].errors // [])),
    stats: {
      expected:   ((.[0].stats.expected // 0)   + (.[1].stats.expected // 0)),
      unexpected: ((.[0].stats.unexpected // 0) + (.[1].stats.unexpected // 0)),
      flaky:      ((.[0].stats.flaky // 0)      + (.[1].stats.flaky // 0)),
      skipped:    ((.[0].stats.skipped // 0)    + (.[1].stats.skipped // 0))
    }
  }' "$main" "$pse" > "$out" 2>/dev/null && rm -f "$main" "$pse"
}

rc=0

# Note: no separate warm-up needed. The first `npx playwright test` invocation below runs
# global-setup, which configures the MCO store (and resets the Docker store only if it was
# on another country — shown with visible progress). The mu-plugin is installed only after
# that, between phases, and survives phase 2's global-setup (same country → no Docker reset,
# and global-setup does not touch mu-plugins).

# ── Full MCO suite (E2E_MCO_FULL): two-phase run ─────────────────────────────
if [ -n "${E2E_MCO_FULL:-}" ]; then
  # Phase 1 — non-PSE tests without mu-plugin (chocustom/chopro/ticket use
  # order-received normally; installing the plugin here would break their redirects).
  for mode in "${_pse_modes[@]}"; do
    echo "[pse] === MCO $mode (non-PSE: chocustom/chopro/ticket) ==="
    # ALLOWLIST INTENCIONAL: lista explicitamente os diretórios MCO não-PSE.
    # Novos diretórios de teste MCO (ex.: tests/mco/wallet/) precisam ser
    # adicionados aqui manualmente — sem isso ficarão fora da cobertura do
    # --with-pse / --release sem nenhum sinal de erro no relatório.
    run_playwright "$mode" "${mode}-nonpse" tests/mco/chocustom/ tests/mco/chopro/ tests/mco/ticket/ || rc=1
    move_blob "${mode}-nonpse"
  done

  # Phase 2 — PSE spec only, with mu-plugin active.
  install_mu_plugin "$PSE_CALLBACK_DOMAIN"
  for mode in "${_pse_modes[@]}"; do
    echo "[pse] === MCO $mode (pse) ==="
    run_playwright "$mode" "${mode}-pse" tests/mco/pse/pse_payment.spec.js || rc=1
    move_blob "${mode}-pse"
  done

  # Merge each mode's two phases into the single MCO-<mode>.json run-all-report expects.
  for mode in "${_pse_modes[@]}"; do
    merge_json "$mode"
  done

# ── Standalone (PSE only) ─────────────────────────────────────────────────────
else
  # Configure the MCO store BEFORE installing the mu-plugin: global-setup may reset the
  # Docker store (if it was on another country), which would wipe the mu-plugin. Running
  # it directly via node is fast and visible — no doomed payment attempt. Once the store
  # is MCO, the PSE invocation's own global-setup won't reset it, so the mu-plugin survives.
  echo "[pse] configuring MCO store..."
  ( cd "$E2E" && SITE=MCO CHECKOUT=classic MP_ENV="${MP_ENV:-test}" \
      node -e "require('./global-setup.js')().then(() => process.exit(0)).catch((e) => { console.error(e); process.exit(1); })" ) || true

  install_mu_plugin "$PSE_CALLBACK_DOMAIN"
  for mode in "${_pse_modes[@]}"; do
    echo "[pse] === MCO $mode (tests/mco/pse/pse_payment.spec.js) ==="
    run_playwright "$mode" "$mode" tests/mco/pse/pse_payment.spec.js || rc=1
    move_blob "$mode"
  done
fi

echo "[pse] done (rc=$rc)"
exit $rc
