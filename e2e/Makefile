.PHONY: run stop tests test-health ensure-running

DC := $(shell if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then echo "docker compose"; elif command -v docker-compose >/dev/null 2>&1; then echo "docker-compose"; else echo "ERROR"; fi)
CONTAINER  := woocommerce-mercadopago-e2e
E2E_DIR    := /var/www/html/wp-content/plugins/woocommerce-mercadopago/e2e

# Fail loudly if DC was not resolved (e.g. docker compose not in PATH at parse time).
# When called from bin/test.sh, DC is passed explicitly on the command line to avoid this.
ifeq ($(DC),ERROR)
$(error "docker compose not found. Install Docker Desktop or pass DC explicitly: make test-health DC='docker compose'")
endif

# Start the container if it is not already running and wait for WordPress to be ready.
ensure-running:
	@if ! docker ps --format '{{.Names}}' | grep -q '^$(CONTAINER)$$'; then \
		echo "[e2e] Container not running — starting..."; \
		$(DC) up -d --build || { echo "[e2e] ERROR: failed to start container."; exit 1; }; \
		echo "[e2e] Waiting for first-time setup to complete (this may take several minutes)..."; \
		for i in $$(seq 1 200); do \
			docker exec $(CONTAINER) test -f /usr/.woocommerce-installed >/dev/null 2>&1 && break; \
			if ! docker ps --format '{{.Names}}' | grep -q '^$(CONTAINER)$$'; then \
				echo "[e2e] ERROR: container exited unexpectedly. Last logs:"; \
				docker logs --tail 20 $(CONTAINER) 2>&1; \
				exit 1; \
			fi; \
			if [ $$(( $$i % 10 )) -eq 0 ]; then \
				echo "[e2e] Still setting up... ($$(($$i * 3))s elapsed) — $$(docker logs --tail 1 $(CONTAINER) 2>&1 | tr -d '\n')"; \
			fi; \
			sleep 3; \
		done; \
		docker exec $(CONTAINER) test -f /usr/.woocommerce-installed >/dev/null 2>&1 \
			|| { echo "[e2e] ERROR: setup did not complete in time."; exit 1; }; \
		echo "[e2e] Waiting for Apache to accept connections..."; \
		ready=0; \
		for i in $$(seq 1 20); do \
			docker exec $(CONTAINER) curl -sf http://localhost >/dev/null 2>&1 && ready=1 && break; \
			sleep 2; \
		done; \
		if [ "$$ready" -eq 0 ]; then \
			echo "[e2e] ERROR: Apache did not become ready in time."; exit 1; \
		fi; \
		echo "[e2e] WordPress is ready."; \
	fi

run:
	$(DC) up -d

stop:
	$(DC) down

# Run all tests (or a specific folder via `make tests folder=mlb`).
# npm install runs first to guarantee dependencies are present.
tests: ensure-running
	docker exec -it $(CONTAINER) sh -c "cd $(E2E_DIR) && npm install --silent && npx playwright install chromium && npx playwright test $(folder)"

# Shortcut: run only the Health Monitor integration tests.
test-health: ensure-running
	docker exec -it $(CONTAINER) sh -c "cd $(E2E_DIR) && npm install --silent && npx playwright install chromium && npx playwright test tests/health"
