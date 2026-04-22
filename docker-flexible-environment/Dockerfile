# ============================================================================
# WooCommerce Mercado Pago — Development Environment
# Supports PHP 7.4 through 8.4 via build arg.
#
# Usage:
#   docker build --build-arg PHP_VERSION=7.4 -t mp-wc-dev .
#   docker build --build-arg PHP_VERSION=8.4 -t mp-wc-dev .
# ============================================================================

ARG PHP_VERSION=7.4
FROM wordpress:php${PHP_VERSION}

ENV WORDPRESS_DB_HOST=localhost
ENV WORDPRESS_DB_USER=wordpress
ENV WORDPRESS_DB_PASSWORD=wordpress
ENV WORDPRESS_DB_NAME=wordpress
ENV WORDPRESS_DEBUG=1
ENV WORDPRESS_CONFIG_EXTRA="define('WP_DEBUG_LOG', true); define('WP_ENVIRONMENT_TYPE', 'local'); define('WP_DEVELOPMENT_MODE', 'plugin');"

# WordPress core: the base image declares VOLUME /var/www/html, so build-time
# writes are discarded. We refresh WP core in the entrypoint instead.
RUN rm -rf /usr/src/wordpress \
    && curl -fsSL "https://wordpress.org/latest.tar.gz" -o /tmp/wordpress.tar.gz \
    && tar -xzf /tmp/wordpress.tar.gz -C /usr/src/ \
    && rm /tmp/wordpress.tar.gz \
    && chown -R www-data:www-data /usr/src/wordpress

# System packages: dev tools + MariaDB (single-container) + Playwright browser deps
RUN apt-get update && apt-get install -y --no-install-recommends \
    less unzip zip git curl wget \
    mariadb-server mariadb-client \
    libnss3 libatk1.0-0 libatk-bridge2.0-0 libcups2 libxkbcommon-x11-0 \
    libgbm1 libasound2 libpangocairo-1.0-0 libxrandr2 libgtk-3-0 \
    libpango-1.0-0 xdg-utils fonts-liberation libwoff1 \
    libopus0 libgstreamer-plugins-base1.0-0 libgstreamer1.0-0 \
    && rm -rf /var/lib/apt/lists/* \
    && mkdir -p /var/run/mysqld \
    && chown -R mysql:mysql /var/run/mysqld \
    && chmod 777 /var/run/mysqld

# Xdebug 3 (supports PHP 7.4–8.4)
# - PHP 7.4: xdebug 3.1.6 (last version supporting 7.4)
# - PHP 8.0+: xdebug latest
# ENABLED by default (mode=debug). The dev only needs to press F5 in VS Code.
# Xdebug only connects when an IDE is listening — no overhead otherwise.
ARG PHP_VERSION
RUN XDEBUG_PKG=$( \
        php_major=$(php -r "echo PHP_MAJOR_VERSION;"); \
        php_minor=$(php -r "echo PHP_MINOR_VERSION;"); \
        if [ "$php_major" = "7" ]; then echo "xdebug-3.1.6"; else echo "xdebug"; fi \
    ) \
    && pecl install "$XDEBUG_PKG" \
    && docker-php-ext-enable xdebug \
    && { \
        echo "[xdebug]"; \
        echo "xdebug.mode=debug"; \
        echo "xdebug.start_with_request=yes"; \
        echo "xdebug.client_host=host.docker.internal"; \
        echo "xdebug.client_port=9003"; \
        echo "xdebug.idekey=VSCODE"; \
        echo "xdebug.log_level=0"; \
    } > /usr/local/etc/php/conf.d/xdebug.ini

# Apache: enable .htaccess (AllowOverride All) and mod_headers (no-cache for dev)
RUN a2enmod headers \
    && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# MySQL config: allow local TCP connections
RUN printf '[mysqld]\nskip-networking=0\nsocket=/var/run/mysqld/mysqld.sock\nbind-address=127.0.0.1\n' \
    >> /etc/mysql/mariadb.conf.d/50-server.cnf

# Database initialization moved to entrypoint.sh (runs at container start,
# after the db-data volume is mounted). Build-time init would be overwritten
# by the volume mount at runtime.

# WP-CLI
RUN curl -fsSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    -o /usr/local/bin/wp && chmod +x /usr/local/bin/wp

# Node.js 20 (for plugin build and E2E tests)
COPY --from=node:20-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:20-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -sf /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -sf /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

# Composer
RUN curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer

# Pre-download plugins and themes (cached in image layer — no download at runtime)
# Themes: top 5 LATAM WooCommerce themes + Storefront (WC official, default)
RUN mkdir -p /usr/src/wp-staging/plugins /usr/src/wp-staging/themes \
    && curl -fsSL "https://downloads.wordpress.org/plugin/woocommerce.latest-stable.zip" \
       -o /usr/src/wp-staging/plugins/woocommerce.zip \
    && curl -fsSL "https://github.com/woocommerce/wc-smooth-generator/releases/latest/download/wc-smooth-generator.zip" \
       -o /usr/src/wp-staging/plugins/wc-smooth-generator.zip \
    && curl -fsSL "https://downloads.wordpress.org/plugin/woocommerce-extra-checkout-fields-for-brazil.latest-stable.zip" \
       -o /usr/src/wp-staging/plugins/woocommerce-extra-checkout-fields-for-brazil.zip \
    && curl -fsSL "https://downloads.wordpress.org/theme/storefront.latest-stable.zip" \
       -o /usr/src/wp-staging/themes/storefront.zip \
    && curl -fsSL "https://downloads.wordpress.org/theme/astra.latest-stable.zip" \
       -o /usr/src/wp-staging/themes/astra.zip \
    && curl -fsSL "https://downloads.wordpress.org/theme/kadence.latest-stable.zip" \
       -o /usr/src/wp-staging/themes/kadence.zip \
    && curl -fsSL "https://downloads.wordpress.org/theme/oceanwp.latest-stable.zip" \
       -o /usr/src/wp-staging/themes/oceanwp.zip \
    && curl -fsSL "https://downloads.wordpress.org/theme/blocksy.latest-stable.zip" \
       -o /usr/src/wp-staging/themes/blocksy.zip \
    && curl -fsSL "https://downloads.wordpress.org/theme/generatepress.latest-stable.zip" \
       -o /usr/src/wp-staging/themes/generatepress.zip \
    && curl -fsSL "https://downloads.wordpress.org/theme/neve.latest-stable.zip" \
       -o /usr/src/wp-staging/themes/neve.zip \
    && curl -fsSL "https://downloads.wordpress.org/theme/hestia.latest-stable.zip" \
       -o /usr/src/wp-staging/themes/hestia.zip

# Copy scripts (outside VOLUME so they persist)
COPY entrypoint.sh /entrypoint.sh
COPY setup-store.sh /setup-store.sh
RUN chmod +x /entrypoint.sh /setup-store.sh

ENTRYPOINT ["/entrypoint.sh"]
