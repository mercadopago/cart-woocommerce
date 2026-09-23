#!/bin/bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TEST_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/wp-cli-wrapper-test.XXXXXX")"
case "$TEST_ROOT" in *wp-cli-wrapper-test.*) ;; *) exit 70 ;; esac

cleanup() {
    find "$TEST_ROOT" -depth -delete 2>/dev/null || true
}
trap cleanup EXIT

BIN_DIR="$TEST_ROOT/bin"
CALL_LOG="$TEST_ROOT/calls.log"
install -d "$BIN_DIR"

cat > "$BIN_DIR/wp" <<'STUB'
#!/bin/bash
printf 'wp %s\n' "$*" >> "$WP_CLI_CALL_LOG"
STUB
cat > "$BIN_DIR/runuser" <<'STUB'
#!/bin/bash
printf 'runuser %s\n' "$*" >> "$WP_CLI_CALL_LOG"
STUB
chmod 700 "$BIN_DIR/wp" "$BIN_DIR/runuser"

PATH="$BIN_DIR:$PATH" WP_CLI_CALL_LOG="$CALL_LOG" \
    "$SCRIPT_DIR/wp-cli.sh" option get home
grep -Fx 'wp --allow-root --path=/var/www/html option get home' "$CALL_LOG" >/dev/null

: > "$CALL_LOG"
PATH="$BIN_DIR:$PATH" WP_CLI_CALL_LOG="$CALL_LOG" E2E_SHARED_ENVIRONMENT=1 \
    "$SCRIPT_DIR/wp-cli.sh" plugin status woocommerce-mercadopago
grep -Fx 'runuser -u www-data -- wp --path=/var/www/html plugin status woocommerce-mercadopago' "$CALL_LOG" >/dev/null

echo 'wp-cli-wrapper: ok'
