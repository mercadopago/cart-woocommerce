#!/bin/bash
set -euo pipefail

# Install flag lives INSIDE the volume so it persists across container recreations.
# make down + make up → data preserved, setup skipped (fast restart)
# make reset         → volume destroyed, flag gone, full setup runs
INSTALL_FLAG="/var/www/html/.mp-store-installed"
SITE_FLAG="/var/www/html/.mp-store-site"
# Local/personal stores bind-mount the repository checkout straight at /woocommerce-mercadopago.
# Shared E2E lanes bind-mount the whole lane read-only at /e2e-artifacts, so the plugin is reached
# through the lane's `current` pointer. Publishing there is an atomic repoint of that symlink on
# the host, which stays visible to a running container precisely because the mount is the lane
# root and not the resolved release directory.
WP_PLUGINS="/var/www/html/wp-content/plugins"
if [ "${E2E_SHARED_ENVIRONMENT:-0}" = "1" ]; then
    PLUGIN_SRC="/e2e-artifacts/current/woocommerce-mercadopago"
else
    PLUGIN_SRC="/woocommerce-mercadopago"
fi
WP="/wp-cli.sh"
SITE="${SITE:-mlb}"
PORT="${PORT:-8080}"
THEME="${THEME:-storefront}"
WP_ADMIN_USER="${WP_ADMIN_USER:-admin}"
WP_ADMIN_PASSWORD="${WP_ADMIN_PASSWORD:-admin}"
WP_ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@test.com}"

case "$SITE" in
    mlb|mla|mlm|mco|mlc|mlu|mpe) ;;
    *)
        echo "[mp-dev] ERROR: unsupported site." >&2
        exit 1
        ;;
esac

if [ "${E2E_SHARED_ENVIRONMENT:-0}" = "1" ]; then
    if [ "$WP_ADMIN_USER" = "admin" ] || [ "$WP_ADMIN_PASSWORD" = "admin" ] || [ "${#WP_ADMIN_PASSWORD}" -lt 20 ]; then
        echo "[mp-dev] ERROR: shared environments require a non-default admin user and a password with at least 20 characters." >&2
        exit 1
    fi
fi

run_wp_config() {
    if [ "${E2E_SHARED_ENVIRONMENT:-0}" != "1" ] || [ ! -f /var/www/html/wp-config.php ]; then
        "$WP" config "$@"
        return
    fi

    # A subshell EXIT trap restores the protected owner/mode even when WP-CLI fails.
    (
        trap 'chown root:www-data /var/www/html/wp-config.php; chmod 640 /var/www/html/wp-config.php' EXIT
        chown www-data:www-data /var/www/html/wp-config.php
        chmod 640 /var/www/html/wp-config.php
        "$WP" config "$@"
    )
}

harden_shared_runtime() {
    [ "${E2E_SHARED_ENVIRONMENT:-0}" = "1" ] || return 0
    [ -f /var/www/html/wp-config.php ] || return 0

    # Legacy persistent volumes may leave wp-config.php owned by root. Allow the
    # unprivileged shared WP-CLI wrapper to update it, then lock it back down.
    # `config delete` removes duplicate legacy definitions before one canonical
    # false value is recreated. A plain `config set` can update the later WordPress
    # default while an earlier custom `true` remains effective.
    debug_config_failed=0
    run_wp_config delete WP_DEBUG --type=constant >/dev/null 2>&1 || true
    run_wp_config set WP_DEBUG false --raw --type=constant >/dev/null || debug_config_failed=1
    run_wp_config delete WP_DEBUG_LOG --type=constant >/dev/null 2>&1 || true
    run_wp_config set WP_DEBUG_LOG false --raw --type=constant >/dev/null || debug_config_failed=1
    run_wp_config delete WP_DEBUG_DISPLAY --type=constant >/dev/null 2>&1 || true
    run_wp_config set WP_DEBUG_DISPLAY false --raw --type=constant >/dev/null || debug_config_failed=1
    if [ "$debug_config_failed" -ne 0 ]; then
        echo "[mp-dev] ERROR: shared WordPress debug configuration could not be hardened." >&2
        return 1
    fi
    rm -f /var/www/html/wp-content/debug.log

    install -d -o www-data -g www-data /var/www/html/wp-content/upgrade
    chown www-data:www-data /var/www/html/wp-content /var/www/html/wp-content/plugins
    if [ -d "$WP_PLUGINS/woocommerce-mercadopago" ] && [ ! -L "$WP_PLUGINS/woocommerce-mercadopago" ]; then
        chown -R www-data:www-data "$WP_PLUGINS/woocommerce-mercadopago"
    fi
    if [ -d /var/www/html/wp-content/languages ]; then
        chown -R www-data:www-data /var/www/html/wp-content/languages
    fi
}

# Build the site URL: use port 80 without explicit port, otherwise include it
if [ "$PORT" = "80" ]; then
    SITE_URL="http://localhost"
else
    SITE_URL="http://localhost:${PORT}"
fi

# ---------- MySQL ----------
# The db-data volume is mounted at /var/lib/mysql.
# On first run it may be empty or contain stale data from the image layer.
# We always ensure the datadir is properly initialized and the wordpress
# database/user exist.

# Initialize datadir if it looks empty or broken
if [ ! -d /var/lib/mysql/mysql ]; then
    echo "[mp-dev] Initializing MariaDB data directory..."
    mysql_install_db --user=mysql --datadir=/var/lib/mysql > /dev/null 2>&1
fi

chown -R mysql:mysql /var/lib/mysql
mysqld --user=mysql --port=3306 --socket=/var/run/mysqld/mysqld.sock &

# Wait for MariaDB to accept connections (polling instead of fixed sleep)
for i in $(seq 1 30); do
    mysqladmin ping --silent 2>/dev/null && break
    sleep 1
done

# Ensure wordpress database and user exist (idempotent)
mysql -u root -e "CREATE DATABASE IF NOT EXISTS wordpress;" 2>/dev/null
mysql -u root -e "CREATE USER IF NOT EXISTS 'wordpress'@'%' IDENTIFIED BY 'wordpress';" 2>/dev/null
mysql -u root -e "GRANT ALL PRIVILEGES ON wordpress.* TO 'wordpress'@'%';" 2>/dev/null
mysql -u root -e "FLUSH PRIVILEGES;" 2>/dev/null
echo "[mp-dev] MariaDB ready."

# ---------- WordPress core (VOLUME mount discards build-time writes) ----------
if [ ! -f /var/www/html/wp-includes/version.php ]; then
    echo "[mp-dev] Copying WordPress core..."
    cp -a /usr/src/wordpress/. /var/www/html/
    chown -R www-data:www-data /var/www/html/
fi

# ---------- First-time setup ----------
if [ ! -f "$INSTALL_FLAG" ]; then
    echo "[mp-dev] First-time setup for site: $SITE"

    # WordPress core
    $WP config create \
        --dbname=wordpress --dbuser=wordpress --dbpass=wordpress --dbhost=127.0.0.1 \
        --extra-php <<'PHPEOF'
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_ENVIRONMENT_TYPE', 'local');
define('WP_DEVELOPMENT_MODE', 'plugin');
define('DISALLOW_FILE_EDIT', true);

/* Reverse proxy HTTPS detection (ngrok, cloudflared, etc.)
 * When behind a tunnel, the proxy terminates SSL and forwards HTTP to Apache.
 * Without this, WordPress generates http:// URLs for assets → mixed content block.
 */
if (
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
) {
    $_SERVER['HTTPS'] = 'on';
}
PHPEOF

    # Shared stores disable debug before loading any third-party plugin code.
    harden_shared_runtime

    printf '%s\n' "$WP_ADMIN_PASSWORD" | $WP core install \
        --url="$SITE_URL" --title="MP Dev Store" \
        --admin_user="$WP_ADMIN_USER" --prompt=admin_password \
        --admin_email="$WP_ADMIN_EMAIL" --skip-email

    # Pretty permalinks (needed for /shop/, /checkout/, etc.)
    $WP rewrite structure '/%postname%/'

    # ---------- WooCommerce + plugins ----------
    echo "[mp-dev] Installing WooCommerce..."
    $WP plugin install /usr/src/wp-staging/plugins/woocommerce.zip --activate
    $WP plugin install /usr/src/wp-staging/plugins/wc-smooth-generator.zip --activate

    # WooCommerce pages (Shop, Cart, Checkout, My Account)
    $WP wc tool run install_pages --user="$WP_ADMIN_USER" 2>/dev/null || true
    $WP eval-file /setup-checkout-pages.php

    # ---------- Themes (install all pre-downloaded, activate default) ----------
    echo "[mp-dev] Installing themes..."
    for theme_zip in /usr/src/wp-staging/themes/*.zip; do
        $WP theme install "$theme_zip" 2>/dev/null || true
    done
    $WP theme activate "${THEME:-storefront}"

    # ---------- Mercado Pago plugin (symlink from host mount) ----------
    echo "[mp-dev] Linking Mercado Pago plugin..."
    ln -sfn "$PLUGIN_SRC" "$WP_PLUGINS/woocommerce-mercadopago"
    $WP plugin activate woocommerce-mercadopago

    # ---------- Country-specific setup ----------
    echo "[mp-dev] Configuring store for: $SITE"
    /setup-store.sh "$SITE"

    # Write .htaccess for pretty permalinks
    cat > /var/www/html/.htaccess <<'HTACCESS'
# Prevent browsers from caching redirects (fixes stale 301 after tunnel-stop)
<IfModule mod_headers.c>
Header always set Cache-Control "no-store, no-cache, must-revalidate" env=!STATIC_ASSET
Header always set Pragma "no-cache" env=!STATIC_ASSET
</IfModule>

# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
HTACCESS
    chown www-data:www-data /var/www/html/.htaccess

    $WP rewrite flush 2>/dev/null || true

    # Remove coming soon page
    $WP option update woocommerce_coming_soon no 2>/dev/null || true

    # Store which site was configured and mark as installed
    echo "$SITE" > "$SITE_FLAG"
    touch "$INSTALL_FLAG"
    echo "[mp-dev] Setup complete."
else
    echo "[mp-dev] Existing store detected. Skipping setup."
fi

# Reapply on every boot so persistent volumes cannot restore unsafe debug or root ownership.
harden_shared_runtime

# Keep persistent shared stores aligned with their country locale. Local/personal stores retain
# the locale selected by the developer and do not gain a fatal wordpress.org dependency on boot.
if [ "${E2E_SHARED_ENVIRONMENT:-0}" = "1" ]; then
    CURRENT_LOCALE="$($WP option get WPLANG 2>/dev/null || true)"
    if [ -z "$CURRENT_LOCALE" ] || ! WP_LANGUAGE_OWNER=www-data \
        /install-woocommerce-locale.sh "$CURRENT_LOCALE"; then
        echo "[mp-dev] ERROR: WooCommerce locale could not be synchronized." >&2
        exit 1
    fi
    echo "[mp-dev] WooCommerce locale synchronized."
fi

# ---------- Auto-link plugin ----------
# Shared lanes: the lane is the single source of truth, so the link is always reasserted. A plain
# `[ ! -e ]` guard cannot do that — for a symlink left dangling by a mount layout change `-e` is
# false while `-L` is true, so the stale link would survive and WordPress would boot with no
# gateway at all. Reasserting also migrates pairs created before the lane-root mount.
# Local/personal stores keep the original behaviour: if a dev deleted the bundled plugin and
# installed their own build/zip (a real directory), or a valid symlink already exists, we leave it
# untouched, so the plugin stays manageable from wp-admin across restarts.
if [ "${E2E_SHARED_ENVIRONMENT:-0}" = "1" ]; then
    if [ ! -d "$PLUGIN_SRC" ] || [ ! -f "$PLUGIN_SRC/woocommerce-mercadopago.php" ]; then
        echo "[mp-dev] ERROR: shared lane artifact missing at $PLUGIN_SRC; seed or publish the lane." >&2
        exit 1
    fi
    ln -sfn "$PLUGIN_SRC" "$WP_PLUGINS/woocommerce-mercadopago"
    echo "[mp-dev] Plugin symlink pointed at the shared lane."
elif [ ! -e "$WP_PLUGINS/woocommerce-mercadopago" ] && [ ! -L "$WP_PLUGINS/woocommerce-mercadopago" ]; then
    ln -sfn "$PLUGIN_SRC" "$WP_PLUGINS/woocommerce-mercadopago"
    echo "[mp-dev] Plugin symlink created."
fi

# ---------- Sync credentials and admin configuration ----------
# Runs after every container recreation. The PHP script reads values directly
# from the process environment, so secrets never appear in WP-CLI arguments or logs.
if ! $WP eval-file /sync-runtime-config.php; then
    echo "[mp-dev] ERROR: runtime configuration could not be synchronized." >&2
    exit 1
fi
echo "[mp-dev] Runtime configuration synchronized."

# ---------- Diretórios de upload/upgrade (idempotente) ----------
mkdir -p /var/www/html/wp-content/uploads /var/www/html/wp-content/upgrade
chown -R www-data:www-data /var/www/html/wp-content/uploads /var/www/html/wp-content/upgrade 2>/dev/null || true

# ---------- Sync runtime wp-config constants ----------
# Applied on every container start (not just first-time setup) so env-var changes
# take effect without requiring a volume reset.
if [ -n "${MP_AUTOMATIC_PAYMENTS_BASE_PATH:-}" ]; then
    run_wp_config set MP_AUTOMATIC_PAYMENTS_BASE_PATH "$MP_AUTOMATIC_PAYMENTS_BASE_PATH" 2>/dev/null || true
    echo "[mp-dev] MP_AUTOMATIC_PAYMENTS_BASE_PATH configured."
fi

# ---------- Start Apache ----------
echo "[mp-dev] Store ready."
exec apache2-foreground
