#!/bin/bash
set -euo pipefail

WP_PATH="${WP_PATH:-/var/www/html}"

if [ "${E2E_SHARED_ENVIRONMENT:-0}" = "1" ]; then
    exec runuser -u www-data -- wp --path="$WP_PATH" "$@"
fi

exec wp --allow-root --path="$WP_PATH" "$@"
