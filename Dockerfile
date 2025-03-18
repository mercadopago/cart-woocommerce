FROM wordpress:php7.4

# download latest wordpress
RUN rm -R /usr/src/wordpress \
    set -eux; \
	\
	curl -o wordpress.tar.gz -fL "https://wordpress.org/latest.tar.gz"; \
	\
	tar -xzf wordpress.tar.gz -C /usr/src/; \
	rm wordpress.tar.gz; \
	\
	chown -R www-data:www-data /usr/src/wordpress; \
	mkdir wp-content; \
	for dir in /usr/src/wordpress/wp-content/*/ cache; do \
		dir="$(basename "${dir%/}")"; \
		mkdir "wp-content/$dir"; \
	done; \
	chown -R www-data:www-data wp-content; \
	chmod -R 777 wp-content

# setup dependencies
RUN apt update; \
    apt install less unzip zip git -y; \
    mkdir wp-content/uploads; \
    chown -R www-data:www-data /var/www

# install node/npm
COPY --from=node:20 /usr/local/bin/node /usr/local/bin/node
COPY --from=node:20 /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm; \
    ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

# install composer
RUN curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer

# install xdebug
RUN pecl install xdebug-3.1.5; \
    docker-php-ext-enable xdebug; \
    touch /var/log/xdebug.log; \
    chown www-data:www-data /var/log/xdebug.log
RUN cat >> /usr/local/etc/php/php.ini <<EOL
upload_max_filesize = 64M
[XDebug]
xdebug.client_host=host.docker.internal
xdebug.start_with_request=yes
xdebug.log=/var/log/xdebug.log
EOL

# install wp-cli
RUN curl -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar; \
    chmod +x /usr/local/bin/wp

# install supervisor
RUN apt install -y supervisor
RUN cat >> /etc/supervisor/conf.d/supervisord.conf <<EOL
[unix_http_server]
file=/var/run/supervisor.sock

[supervisord]
nodaemon=true
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid

[rpcinterface:supervisor]
supervisor.rpcinterface_factory=supervisor.rpcinterface:make_main_rpcinterface

[program:app]
command=docker-entrypoint.sh apache2-foreground
killasgroup=true
stopasgroup=true
stdout_logfile=/var/log/app.log
redirect_stderr=true

[program:watch-build]
directory=/var/www/html/wp-content/plugins/woocommerce-mercadopago
command=npm run watch:build
killasgroup=true
stopasgroup=true
stdout_logfile=/var/log/watch-build.log
redirect_stderr=true

[program:watch-logs]
directory=/var/www/html/wp-content/plugins/woocommerce-mercadopago
command=npm run watch:logs
killasgroup=true
stopasgroup=true
stdout_logfile=/var/log/watch-logs.log
redirect_stderr=true

[program:watch-make-mo]
directory=/var/www/html/wp-content/plugins/woocommerce-mercadopago
command=npm run watch:make-mo
killasgroup=true
stopasgroup=true
stdout_logfile=/var/log/watch-make-mo.log
redirect_stderr=true
EOL

ENTRYPOINT ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
