INSTALL_FLAG="/usr/.woocommerce-installed"
TEMP_PLUGIN_DIR="/woocommerce-mercadopago"
WP_PLUGIN_DIR="/var/www/html/wp-content/plugins/"

mysqld --port=3306 --socket=/var/run/mysqld/mysqld.sock &
sleep 5

if [ ! -f "$INSTALL_FLAG" ]; then
    wp plugin install woocommerce --activate --allow-root; \
    wp plugin install https://github.com/woocommerce/wc-smooth-generator/releases/latest/download/wc-smooth-generator.zip --activate --allow-root; \
    wp wc generate products 3 --allow-root; \
    wp theme install storefront --activate --allow-root; \
    wp theme delete --all --allow-root; \
    wp option update woocommerce_coming_soon no --allow-root
    ln -sfn "$TEMP_PLUGIN_DIR" "$WP_PLUGIN_DIR"
    wp plugin activate woocommerce-mercadopago --allow-root
    touch "$INSTALL_FLAG"
fi

exec apache2-foreground
