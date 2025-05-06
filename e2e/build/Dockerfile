# Wordpress com Apache e versão
FROM wordpress:php7.4

# Variáveis de ambiente
ENV WORDPRESS_DB_HOST=localhost
ENV WORDPRESS_DB_USER=wordpress
ENV WORDPRESS_DB_PASSWORD=wordpress
ENV WORDPRESS_DB_NAME=wordpress
ENV WORDPRESS_DEBUG=1
ENV WORDPRESS_CONFIG_EXTRA="define('WP_DEBUG_LOG', true); define('WP_ENVIRONMENT_TYPE', 'local'); define('WP_DEVELOPMENT_MODE', 'plugin');"

# Instala o WordPress na versão mais recente
RUN rm -R /usr/src/wordpress; \
    set -eux; \
    curl -o wordpress.tar.gz -fL "https://wordpress.org/latest.tar.gz"; \
    tar -xzf wordpress.tar.gz -C /usr/src/; \
    rm wordpress.tar.gz; \
    chown -R www-data:www-data /usr/src/wordpress; \
    rm -rf wp-content; \
    mkdir wp-content; \
    for dir in /usr/src/wordpress/wp-content/*/ cache; do \
        dir="$(basename "${dir%/}")"; \
        mkdir -p "wp-content/$dir"; \
    done; \
    chown -R www-data:www-data wp-content; \
    chmod -R 777 wp-content;

# Copiar o WordPress para o diretório correto
RUN cp -R /usr/src/wordpress/* /var/www/html/ \
	&& chown -R www-data:www-data /var/www/ \
	&& chmod -R 755 /var/www/html

# Instalar pacotes necessários
RUN apt update && apt install -y \
    less unzip zip git curl wget mariadb-server mariadb-client \
    libnss3 libatk1.0-0 libatk-bridge2.0-0 libcups2 libxkbcommon-x11-0 \
    libgbm1 libasound2 libpangocairo-1.0-0 libxrandr2 libgtk-3-0 \
    libpango-1.0-0 xdg-utils fonts-liberation libwoff1 \
    libopus0 libgstreamer-plugins-base1.0-0 libgstreamer1.0-0 \
    && mkdir -p /var/run/mysqld \
    && chown -R mysql:mysql /var/run/mysqld \
    && chmod 777 /var/run/mysqld

# Configura o MySQL para aceitar conexões locais
RUN echo "[mysqld]" >> /etc/mysql/mariadb.conf.d/50-server.cnf \
    && echo "skip-networking=0" >> /etc/mysql/mariadb.conf.d/50-server.cnf \
    && echo "socket=/var/run/mysqld/mysqld.sock" >> /etc/mysql/mariadb.conf.d/50-server.cnf \
    && echo "bind-address = 127.0.0.1" >> /etc/mysql/mariadb.conf.d/50-server.cnf

# Inicializa o banco de dados e cria o usuário do WordPress
RUN mysqld --port=3306 --socket=/var/run/mysqld/mysqld.sock & sleep 5 \
    && mysql -u root -e "CREATE DATABASE IF NOT EXISTS ${WORDPRESS_DB_NAME};" \
    && mysql -u root -e "CREATE USER IF NOT EXISTS '${WORDPRESS_DB_USER}'@'%' IDENTIFIED BY '${WORDPRESS_DB_PASSWORD}';" \
    && mysql -u root -e "GRANT ALL PRIVILEGES ON ${WORDPRESS_DB_NAME}.* TO '${WORDPRESS_DB_USER}'@'%';" \
    && mysql -u root -e "FLUSH PRIVILEGES;"

# Instalar Node.js e npm
COPY --from=node:20 /usr/local/bin/node /usr/local/bin/node
COPY --from=node:20 /usr/local/lib/node_modules /usr/local/lib/node_modules

RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

# Instalar Composer
RUN curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer

# Instalar WP-CLI
RUN curl -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && \
    chmod +x /usr/local/bin/wp

# Instalar o WordPress (aguardando o banco de dados estar pronto)
RUN cd /var/www/html/ && \
    mysqld --port=3306 --socket=/var/run/mysqld/mysqld.sock & sleep 5 && \
    wp config create --dbname=wordpress --dbuser=wordpress --dbpass=wordpress --dbhost=127.0.0.1 --allow-root; \
    wp core install --url=localhost:8765 --title=Local --admin_user=admin --admin_password=admin --admin_email=admin@test.com --skip-email --allow-root

# Instalar Playwright
RUN npm install -g playwright \
    && playwright install --with-deps chromium

# Copia o script de inicialização
COPY ./build/entrypoint.sh entrypoint.sh
RUN chmod +x entrypoint.sh

# Executa o script de inicialização
ENTRYPOINT ["/entrypoint.sh"]
