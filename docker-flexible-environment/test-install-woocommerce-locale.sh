#!/bin/bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TEST_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/woocommerce-locale-test.XXXXXX")"
case "$TEST_ROOT" in *woocommerce-locale-test.*) ;; *) exit 70 ;; esac

cleanup() {
    find "$TEST_ROOT" -depth -delete 2>/dev/null || true
}
trap cleanup EXIT

WP_STUB="$TEST_ROOT/wp"
CALL_LOG="$TEST_ROOT/wp-calls.log"
cat > "$WP_STUB" <<'STUB'
#!/bin/bash
set -euo pipefail
locale="${!#}"
printf '%s\n' "$locale" >> "$WP_CALL_LOG"
if [ "$locale" = "${WP_FAIL_LOCALE:-}" ]; then
    exit 1
fi
touch "$WP_LANGUAGE_PLUGIN_DIR/woocommerce-$locale.mo"
touch "$WP_LANGUAGE_PLUGIN_DIR/woocommerce-$locale-script.json"
STUB
chmod 700 "$WP_STUB"

run_installer() {
    local locale="$1" directory="$2" fail_locale="${3:-}"
    install -d "$directory"
    WP_BIN="$WP_STUB" \
    WP_PATH="$TEST_ROOT/wordpress" \
    WP_LANGUAGE_PLUGIN_DIR="$directory" \
    WP_CALL_LOG="$CALL_LOG" \
    WP_FAIL_LOCALE="$fail_locale" \
        "$SCRIPT_DIR/install-woocommerce-locale.sh" "$locale"
}

direct_dir="$TEST_ROOT/direct"
run_installer es_AR "$direct_dir"
[ -f "$direct_dir/woocommerce-es_AR.mo" ]
[ "$(wc -l < "$CALL_LOG" | tr -d ' ')" = "1" ]

fallback_dir="$TEST_ROOT/fallback"
run_installer es_UY "$fallback_dir" es_UY
[ -L "$fallback_dir/woocommerce-es_UY.mo" ]
[ "$(readlink "$fallback_dir/woocommerce-es_UY.mo")" = "woocommerce-es_ES.mo" ]
[ -L "$fallback_dir/woocommerce-es_UY-script.json" ]
[ "$(tail -2 "$CALL_LOG" | tr '\n' ' ')" = "es_UY es_ES " ]

calls_before="$(wc -l < "$CALL_LOG" | tr -d ' ')"
run_installer es_UY "$fallback_dir" es_UY
[ "$(wc -l < "$CALL_LOG" | tr -d ' ')" = "$calls_before" ]

if run_installer invalid_LOCALE "$TEST_ROOT/invalid"; then
    echo 'invalid locale unexpectedly accepted' >&2
    exit 1
fi

echo 'install-woocommerce-locale: ok'
