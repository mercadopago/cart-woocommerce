#!/bin/bash
set -euo pipefail

# Install flag lives INSIDE the volume so it persists across container recreations.
# make down + make up → data preserved, setup skipped (fast restart)
# make reset         → volume destroyed, flag gone, full setup runs
INSTALL_FLAG="/var/www/html/.mp-store-installed"
SITE_FLAG="/var/www/html/.mp-store-site"
PLUGIN_SRC="/woocommerce-mercadopago"
WP_PLUGINS="/var/www/html/wp-content/plugins"
WP="wp --allow-root --path=/var/www/html"
SITE="${SITE:-mlb}"
PORT="${PORT:-8080}"
THEME="${THEME:-storefront}"

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
mysqld --port=3306 --socket=/var/run/mysqld/mysqld.sock &

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

    $WP core install \
        --url="$SITE_URL" --title="MP Dev Store" \
        --admin_user=admin --admin_password=admin \
        --admin_email=admin@test.com --skip-email

    # Pretty permalinks (needed for /shop/, /checkout/, etc.)
    $WP rewrite structure '/%postname%/'

    # ---------- WooCommerce + plugins ----------
    echo "[mp-dev] Installing WooCommerce..."
    $WP plugin install /usr/src/wp-staging/plugins/woocommerce.zip --activate
    $WP plugin install /usr/src/wp-staging/plugins/wc-smooth-generator.zip --activate

    # WooCommerce pages (Shop, Cart, Checkout, My Account)
    $WP wc tool run install_pages --user=admin 2>/dev/null || true

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
    echo "[mp-dev] Existing store detected ($(cat "$SITE_FLAG" 2>/dev/null || echo 'unknown')). Skipping setup."
fi

# ---------- Ensure plugin symlink (always, even on restart) ----------
# The symlink target is inside the volume, but the source mount may change
if [ ! -L "$WP_PLUGINS/woocommerce-mercadopago" ] || \
   [ "$(readlink "$WP_PLUGINS/woocommerce-mercadopago")" != "$PLUGIN_SRC" ]; then
    ln -sfn "$PLUGIN_SRC" "$WP_PLUGINS/woocommerce-mercadopago"
    echo "[mp-dev] Plugin symlink refreshed."
fi

# ---------- Start Apache ----------
echo "[mp-dev] Store ready at $SITE_URL"
exec apache2-foreground
