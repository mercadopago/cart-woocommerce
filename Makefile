.PHONY: build
.SILENT: install make-mo update-po

-include .env

DC := $(shell if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then echo "docker compose"; elif command -v docker-compose >/dev/null 2>&1; then echo "docker-compose"; else echo "ERROR"; fi)

# Fail if neither docker compose nor docker-compose is available
ifeq ($(DOCKER_COMPOSE),ERROR)
$(error "Neither 'docker compose' nor 'docker-compose' is available. Please install Docker Compose.")
endif

DCE			:= $(DC) exec -w /var/www/html/wp-content/plugins/woocommerce-mercadopago
WP_CLI		:= $(DCE) -u www-data app wp
APP			:= $(DCE) app
WP_CLI_SUDO	:= $(APP) wp --allow-root

build:
	./bin/create-release-zip.sh

watch:
	npm run watch:release

release:
	./bin/setup-release.sh

install:
	if [ ! -f .env ]; then \
		cp .env.example .env; \
		$(MAKE) install; \
	else \
		rm -f logs/*.log; \
		$(DC) down -v; \
		$(DC) up -d; \
		until $(WP_CLI) core install --url=localhost --title=Local --admin_user=$(ADMIN_USER) --admin_password=$(ADMIN_PASSWORD) --admin_email=$(ADMIN_EMAIL) --skip-email; do \
			sleep 1; \
		done; \
		if [ ! -d vendor ]; then \
			$(APP) composer install; \
		fi; \
		if [ ! -d node_modules ]; then \
			$(APP) npm install; \
		fi; \
		$(WP_CLI) plugin install woocommerce --activate; \
		$(WP_CLI) plugin install https://github.com/woocommerce/wc-smooth-generator/releases/latest/download/wc-smooth-generator.zip --activate; \
		$(WP_CLI) wc generate products 3; \
		$(WP_CLI) plugin activate woocommerce-mercadopago; \
		$(WP_CLI) theme install storefront --activate; \
		$(WP_CLI) theme delete --all; \
	fi

run:
	$(DC) up -d

stop:
	$(DC) down

update-po:
	$(APP) npm run pot
	for i in es pt; do \
		FILE=i18n/languages/woocommerce-mercadopago-$$i.po; \
		$(WP_CLI_SUDO) i18n update-po i18n/languages/woocommerce-mercadopago.pot $$FILE; \
		sed -i \
			'1 i\# Copyright (C) 2024 woocommerce-mercadopago\n# This file is distributed under the same license as the woocommerce-mercadopago package.' \
			$$FILE; \
	done

make-mo:
	$(WP_CLI_SUDO) i18n make-mo i18n/languages i18n/languages

switch-language:
	$(WP_CLI) language core install $(lang) --activate
	$(WP_CLI) language plugin install $(lang) --all
