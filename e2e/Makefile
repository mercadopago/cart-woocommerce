.PHONY: install tests run stop

DC := $(shell if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then echo "docker compose"; elif command -v docker-compose >/dev/null 2>&1; then echo "docker-compose"; else echo "ERROR"; fi)

# Fail if neither docker compose nor docker-compose is available
ifeq ($(DOCKER_COMPOSE),ERROR)
$(error "Neither 'docker compose' nor 'docker-compose' is available. Please install Docker Compose.")
endif

run:
	$(DC) up -d

stop:
	$(DC) down

tests:
	docker exec -it woocommerce-mercadopago-e2e sh -c "cd /var/www/html/wp-content/plugins/woocommerce-mercadopago/e2e && npx playwright test $(folder)"
