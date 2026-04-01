INSTALL_FLAG="/usr/.woocommerce-installed"
TEMP_PLUGIN_DIR="/woocommerce-mercadopago"
WP_PLUGIN_DIR="/var/www/html/wp-content/plugins/"

mysqld --port=3306 --socket=/var/run/mysqld/mysqld.sock &
sleep 5

# Copy WordPress core files to the web root if not yet present.
# The base image (wordpress:php7.4) declares VOLUME /var/www/html, so any writes
# made during the Docker build are discarded. The actual copy must happen at runtime,
# after the anonymous volume is mounted by the container engine.
if [ ! -f /var/www/html/wp-includes/version.php ]; then
    cp -R /usr/src/wordpress/. /var/www/html/
    chown -R www-data:www-data /var/www/html/
fi

if [ ! -f "$INSTALL_FLAG" ]; then
    wp config create --dbname=wordpress --dbuser=wordpress --dbpass=wordpress --dbhost=127.0.0.1 --allow-root
    wp core install --url=localhost --title=Local --admin_user=admin --admin_password=admin --admin_email=admin@test.com --skip-email --allow-root
    wp plugin install /usr/src/wp-staging/plugins/woocommerce.zip --activate --allow-root
    wp plugin install /usr/src/wp-staging/plugins/wc-smooth-generator.zip --activate --allow-root
    wp wc generate products 3 --allow-root
    wp theme install /usr/src/wp-staging/themes/storefront.zip --activate --allow-root
    wp theme delete --all --allow-root
    wp option update woocommerce_coming_soon no --allow-root
    ln -sfn "$TEMP_PLUGIN_DIR" "$WP_PLUGIN_DIR"
    wp plugin activate woocommerce-mercadopago --allow-root
    cd "$TEMP_PLUGIN_DIR/e2e" && npm install --silent
    touch "$INSTALL_FLAG"
fi

exec apache2-foreground
