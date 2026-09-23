#!/bin/bash
set -euo pipefail

LOCALE="${1:-}"
WP_BIN="${WP_BIN:-/wp-cli.sh}"
WP_PATH="${WP_PATH:-/var/www/html}"
LANGUAGE_PLUGIN_DIR="${WP_LANGUAGE_PLUGIN_DIR:-$WP_PATH/wp-content/languages/plugins}"
LANGUAGE_OWNER="${WP_LANGUAGE_OWNER:-}"

set_language_owner() {
    [ -n "$LANGUAGE_OWNER" ] || return 0
    chown "$LANGUAGE_OWNER:$LANGUAGE_OWNER" "$@"
}

case "$LOCALE" in
    pt_BR|es_AR|es_CL|es_MX|es_CO|es_UY|es_PE) ;;
    *)
        echo "[mp-dev] ERROR: unsupported WooCommerce locale." >&2
        exit 64
        ;;
esac

TARGET_MO="$LANGUAGE_PLUGIN_DIR/woocommerce-$LOCALE.mo"
if [ -f "$TARGET_MO" ]; then
    exit 0
fi

if "$WP_BIN" language plugin install woocommerce "$LOCALE" >/dev/null 2>&1 \
    && [ -f "$TARGET_MO" ]; then
    exit 0
fi

case "$LOCALE" in
    es_UY|es_PE) FALLBACK_LOCALE=es_ES ;;
    *)
        echo "[mp-dev] ERROR: WooCommerce translation is unavailable for $LOCALE." >&2
        exit 1
        ;;
esac

"$WP_BIN" language plugin install woocommerce "$FALLBACK_LOCALE" >/dev/null
install -d "$LANGUAGE_PLUGIN_DIR"
set_language_owner "$LANGUAGE_PLUGIN_DIR"

linked=0
for source in "$LANGUAGE_PLUGIN_DIR"/woocommerce-"$FALLBACK_LOCALE"*; do
    [ -f "$source" ] || continue
    source_name="$(basename "$source")"
    target_name="${source_name/woocommerce-$FALLBACK_LOCALE/woocommerce-$LOCALE}"
    ln -sfn "$source_name" "$LANGUAGE_PLUGIN_DIR/$target_name"
    if [ -n "$LANGUAGE_OWNER" ]; then
        chown -h "$LANGUAGE_OWNER:$LANGUAGE_OWNER" "$LANGUAGE_PLUGIN_DIR/$target_name"
    fi
    linked=1
done

if [ "$linked" -ne 1 ] || [ ! -f "$TARGET_MO" ]; then
    echo "[mp-dev] ERROR: WooCommerce fallback translation could not be installed for $LOCALE." >&2
    exit 1
fi

echo "[mp-dev] WooCommerce locale $LOCALE uses the explicit $FALLBACK_LOCALE fallback."
