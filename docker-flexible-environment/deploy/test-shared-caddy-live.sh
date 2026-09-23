#!/bin/bash
set -euo pipefail

SITES=(mla mlb mlc mlm mco mlu mpe)
ENVIRONMENTS=(staging homol)
BLOCKED_PATHS=(
    /wp-login.php
    /wp-login.php/path-info
    /xmlrpc.php
    /xmlrpc.php/path-info
    /wp-admin
    /wp-admin/
    /wp-content/debug.log
    /wp-content/debug.log/path-info
)

request_status() {
    curl --silent --show-error --output /dev/null --write-out '%{http_code}' \
        --connect-timeout 10 --max-time 20 "$1"
}

for environment in "${ENVIRONMENTS[@]}"; do
    for site in "${SITES[@]}"; do
        origin="https://e2e-${environment}-${site}.ppolimpo.io"
        shop_status="$(request_status "$origin/shop/")"
        case "$shop_status" in 2??|3??) ;; *)
            echo "[E2E] ERROR: $origin/shop/ returned HTTP $shop_status." >&2
            exit 1
        esac

        ajax_status="$(request_status "$origin/wp-admin/admin-ajax.php")"
        case "$ajax_status" in 2??|3??|400) ;; *)
            echo "[E2E] ERROR: admin-ajax is not publicly reachable on $origin (HTTP $ajax_status)." >&2
            exit 1
        esac

        for blocked_path in "${BLOCKED_PATHS[@]}"; do
            blocked_status="$(request_status "$origin$blocked_path")"
            if [ "$blocked_status" != "403" ]; then
                echo "[E2E] ERROR: $origin$blocked_path returned HTTP $blocked_status instead of 403." >&2
                exit 1
            fi
        done
        echo "[E2E] Caddy boundary verified: ${environment}/${site}."
    done
done

echo '[E2E] Live Caddy boundary verified for all 14 stores.'
