# ============================================================================
# WooCommerce Mercado Pago — Development Environment
#
# Lifecycle:
#   make up                      Start MLB store (PHP 7.4, Storefront) at localhost:8080
#   make up SITE=mla             Start Argentine store
#   make up SITE=mlb PHP=8.4     Brazilian store with PHP 8.4
#   make up THEME=kadence        Brazilian store with Kadence theme
#   make up SITE=mla THEME=oceanwp  Argentine store with OceanWP
#   make down                    Stop container (data preserved)
#   make reset                   Destroy everything and recreate
#   make reset SITE=mla          Destroy and recreate as Argentine store
#
# Available themes (pre-downloaded, instant install):
#   storefront    WooCommerce official (default)
#   astra         Most popular free WC theme worldwide
#   kadence       Modern Gutenberg-native, deep WC customization
#   oceanwp       Feature-rich free tier (quick view, wishlist)
#   blocksy       Block-editor-first, excellent WC features
#   generatepress Ultra-lightweight (<10KB), fast on slow connections
#   neve          Lightweight, fast, good for simple stores
#   hestia        One-page design, good for small stores
#
# HTTPS tunnel (required for MP notifications/webhooks):
#   make tunnel                  Start tunnel → HTTPS URL (auto-detects cloudflared > ngrok)
#   make tunnel TUNNEL=ngrok     Force ngrok instead of cloudflared
#   make tunnel-stop             Stop the tunnel
#   make tunnel-url              Show current tunnel URL
#
# Development:
#   make shell                   Shell into the container
#   make logs                    Tail container logs
#   make theme THEME=astra       Switch active theme
#   make checkout-classic        Switch to Classic checkout (shortcode)
#   make checkout-blocks         Switch to Blocks checkout (default WC 8+)
#   make git-info                Show git remote, branch, status inside container
#   make build                   Build plugin inside container
#   make info                    Show store URL, PHP version, active site
#
# Debugging (Xdebug):
#   make debug-on                Enable Xdebug step debugging (breakpoints)
#   make debug-off               Disable Xdebug (restore full performance)
#   make debug-status            Show current Xdebug mode
#
# Testing:
#   make e2e                     Run all E2E tests
#   make e2e TESTS=mlb/pix       Run specific E2E test folder
#
# Sites: mlb (Brazil), mla (Argentina), mlm (Mexico), mco (Colombia),
#        mlc (Chile), mlu (Uruguay), mpe (Peru)
#
# Git workflow:
#   The plugin directory IS your git repo (mounted from host via volume).
#   Work from the HOST — edits reflect instantly in the container.
#
#   cd ..                          ← you are in the plugin root (git repo)
#   git checkout -b feature/PSW-XXXX
#   # ... edit files, run tests ...
#   cd docker && make build        ← rebuild JS/CSS if needed
#   git add . && git commit
#   git push origin feature/PSW-XXXX
# ============================================================================

SITE       ?= mlb
PHP        ?= 7.4
PORT       ?= 8080
THEME      ?= storefront
TESTS      ?=
TUNNEL     ?= $(shell if command -v cloudflared >/dev/null 2>&1; then echo "cloudflared"; elif command -v ngrok >/dev/null 2>&1; then echo "ngrok"; else echo "none"; fi)
NGROK_DOMAIN ?= $(shell cat .env 2>/dev/null | grep '^NGROK_DOMAIN=' | cut -d= -f2)
CONTAINER  := mp-wc-dev
TUNNEL_PID := .tunnel.pid
DC_BIN     := $(shell if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then echo "docker compose"; elif command -v docker-compose >/dev/null 2>&1; then echo "docker-compose"; else echo "ERROR"; fi)
ifeq ($(DC_BIN),ERROR)
$(error "docker compose not found. Install Docker Desktop or docker-compose.")
endif
DC         := SITE=$(SITE) PHP_VERSION=$(PHP) PORT=$(PORT) THEME=$(THEME) $(DC_BIN)
WP         := docker exec $(CONTAINER) wp --allow-root
E2E_DIR    := /woocommerce-mercadopago/e2e

.PHONY: up down reset shell logs theme checkout-classic checkout-blocks e2e build info status tunnel tunnel-stop tunnel-url git-info debug-on debug-off debug-status

# ---------- Lifecycle ----------

up:
	@if [ -f .current-site ] && [ "$$(cat .current-site)" != "$(SITE)-php$(PHP)-$(THEME)" ]; then \
		echo "[mp-dev] Config changed (was: $$(cat .current-site), now: $(SITE)-php$(PHP)-$(THEME)). Resetting..."; \
		$(MAKE) _destroy; \
	fi
	@echo "$(SITE)-php$(PHP)-$(THEME)" > .current-site
	$(DC) up -d --build
	@echo "[mp-dev] Waiting for WordPress to be ready..."
	@for i in $$(seq 1 120); do \
		docker exec $(CONTAINER) test -f /var/www/html/.mp-store-installed >/dev/null 2>&1 && break; \
		if [ $$(( $$i % 15 )) -eq 0 ]; then \
			echo "[mp-dev] Still setting up... ($${i}s elapsed)"; \
		fi; \
		sleep 1; \
	done
	@docker exec $(CONTAINER) test -f /var/www/html/.mp-store-installed >/dev/null 2>&1 \
		|| { echo "[mp-dev] ERROR: Setup did not complete. Run 'make logs' to debug."; exit 1; }
	@echo ""
	@echo "============================================"
	@echo "  Store ready: http://localhost:$(PORT)/shop"
	@echo "  Admin:       http://localhost:$(PORT)/wp-admin"
	@echo "  Credentials: admin / admin"
	@echo "  Site:        $(SITE) | PHP $(PHP) | $(THEME)"
	@echo "============================================"

down:
	$(DC) down

reset:
	@$(MAKE) _destroy
	@$(MAKE) up

_destroy:
	$(DC) down -v --remove-orphans 2>/dev/null || true
	@rm -f .current-site

# ---------- Development ----------

shell:
	docker exec -it $(CONTAINER) bash

logs:
	docker logs -f $(CONTAINER)

info:
	@echo "Container: $(CONTAINER)"
	@if [ -f .tunnel-url ]; then \
		echo "URL:       $$(cat .tunnel-url)/shop (HTTPS tunnel)"; \
		echo "Admin:     $$(cat .tunnel-url)/wp-admin"; \
	else \
		echo "URL:       http://localhost:$(PORT)/shop"; \
		echo "Admin:     http://localhost:$(PORT)/wp-admin"; \
	fi
	@$(WP) --info 2>/dev/null | grep -E "PHP version|WordPress version" || true
	@echo "Site:      $$(cat .current-site 2>/dev/null || echo 'not configured')"
	@echo "WC:        $$( $(WP) plugin get woocommerce --field=version 2>/dev/null || echo 'unknown')"
	@echo "MP Plugin: $$( $(WP) plugin get woocommerce-mercadopago --field=status 2>/dev/null || echo 'unknown')"
	@echo "Git:       $$( docker exec $(CONTAINER) bash -c 'cd /woocommerce-mercadopago && git branch --show-current' 2>/dev/null || echo 'unknown')"
	@if [ -f .tunnel-url ]; then echo "Tunnel:    $$(cat .tunnel-url)"; else echo "Tunnel:    off (run 'make tunnel' for HTTPS)"; fi

status:
	@$(WP) plugin list --format=table 2>/dev/null || echo "Container not running"

# ---------- Theme switching ----------

theme:
	$(WP) theme activate $(THEME)
	@echo "[mp-dev] Active theme: $(THEME)"

# ---------- Checkout mode ----------

checkout-classic:
	$(WP) eval ' \
		$$id = wc_get_page_id("checkout"); \
		wp_update_post(array( \
			"ID" => $$id, \
			"post_content" => "<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->" \
		)); \
	'
	@echo "[mp-dev] Checkout switched to Classic (shortcode)"

checkout-blocks:
	@$(WP) eval ' \
		$$id = wc_get_page_id("checkout"); \
		$$content = "<!-- wp:woocommerce/checkout -->" \
			. "<div class=\"wp-block-woocommerce-checkout alignwide wc-block-checkout is-loading\">" \
			. "<!-- wp:woocommerce/checkout-fields-block --><div class=\"wp-block-woocommerce-checkout-fields-block\">" \
			. "<!-- wp:woocommerce/checkout-express-payment-block --><div class=\"wp-block-woocommerce-checkout-express-payment-block\"></div><!-- /wp:woocommerce/checkout-express-payment-block -->" \
			. "<!-- wp:woocommerce/checkout-contact-information-block --><div class=\"wp-block-woocommerce-checkout-contact-information-block\"></div><!-- /wp:woocommerce/checkout-contact-information-block -->" \
			. "<!-- wp:woocommerce/checkout-shipping-address-block --><div class=\"wp-block-woocommerce-checkout-shipping-address-block\"></div><!-- /wp:woocommerce/checkout-shipping-address-block -->" \
			. "<!-- wp:woocommerce/checkout-billing-address-block --><div class=\"wp-block-woocommerce-checkout-billing-address-block\"></div><!-- /wp:woocommerce/checkout-billing-address-block -->" \
			. "<!-- wp:woocommerce/checkout-shipping-methods-block --><div class=\"wp-block-woocommerce-checkout-shipping-methods-block\"></div><!-- /wp:woocommerce/checkout-shipping-methods-block -->" \
			. "<!-- wp:woocommerce/checkout-payment-block --><div class=\"wp-block-woocommerce-checkout-payment-block\"></div><!-- /wp:woocommerce/checkout-payment-block -->" \
			. "<!-- wp:woocommerce/checkout-additional-information-block --><div class=\"wp-block-woocommerce-checkout-additional-information-block\"></div><!-- /wp:woocommerce/checkout-additional-information-block -->" \
			. "<!-- wp:woocommerce/checkout-order-note-block --><div class=\"wp-block-woocommerce-checkout-order-note-block\"></div><!-- /wp:woocommerce/checkout-order-note-block -->" \
			. "<!-- wp:woocommerce/checkout-terms-block --><div class=\"wp-block-woocommerce-checkout-terms-block\"></div><!-- /wp:woocommerce/checkout-terms-block -->" \
			. "<!-- wp:woocommerce/checkout-actions-block --><div class=\"wp-block-woocommerce-checkout-actions-block\"></div><!-- /wp:woocommerce/checkout-actions-block -->" \
			. "</div><!-- /wp:woocommerce/checkout-fields-block -->" \
			. "<!-- wp:woocommerce/checkout-totals-block --><div class=\"wp-block-woocommerce-checkout-totals-block\">" \
			. "<!-- wp:woocommerce/checkout-order-summary-block --><div class=\"wp-block-woocommerce-checkout-order-summary-block\"></div><!-- /wp:woocommerce/checkout-order-summary-block -->" \
			. "</div><!-- /wp:woocommerce/checkout-totals-block -->" \
			. "</div><!-- /wp:woocommerce/checkout -->"; \
		wp_update_post(array("ID" => $$id, "post_content" => $$content)); \
	'
	@echo "[mp-dev] Checkout switched to Blocks (with inner blocks)"

# ---------- E2E tests ----------
# CHROMIUM_PATH: full Chromium binary (not headless_shell) needed for WC Blocks
#   checkout which uses crypto.randomUUID() requiring Secure Context.
CHROMIUM_PATH := /root/.cache/ms-playwright/chromium-1161/chrome-linux/chrome

CHECKOUT   ?= classic

e2e:
	@echo "[mp-dev] Running E2E tests for $(SITE):$(CHECKOUT) from host..."
	cd ../e2e && SITE=$(shell echo $(SITE) | tr '[:lower:]' '[:upper:]') npm run test:$(SITE):$(CHECKOUT)

e2e-docker:
	@echo "[mp-dev] Configuring URLs for E2E (Chromium cannot reach localhost)..."
	@$(WP) option update siteurl "http://host.docker.internal:$(PORT)" 2>/dev/null || true
	@$(WP) option update home "http://host.docker.internal:$(PORT)" 2>/dev/null || true
	docker exec $(CONTAINER) sh -c " \
		cd $(E2E_DIR) && \
		npm install --silent && \
		npx playwright install chromium && \
		SHOP_URL=http://host.docker.internal:$(PORT)/shop \
		PORT=$(PORT) \
		INSIDE_CONTAINER=1 \
		PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH=$(CHROMIUM_PATH) \
		npx playwright test $(TESTS) \
	"
	@echo "[mp-dev] Restoring local URLs..."
	@$(WP) option update siteurl "http://localhost:$(PORT)" 2>/dev/null || true
	@$(WP) option update home "http://localhost:$(PORT)" 2>/dev/null || true

e2e-reset:
	@echo "[mp-dev] Resetting store for $(SITE) and running E2E tests..."
	@$(MAKE) reset
	cd ../e2e && SITE=$(shell echo $(SITE) | tr '[:lower:]' '[:upper:]') npm run test:$(SITE):$(CHECKOUT)

e2e-all:
	@for site in mlb mla mlm mco mlc mlu mpe; do \
		echo "\n[mp-dev] ====== Running E2E for $$site:$(CHECKOUT) ======"; \
		$(MAKE) reset SITE=$$site && \
		cd ../e2e && SITE=$$(echo $$site | tr '[:lower:]' '[:upper:]') npm run test:$$site:$(CHECKOUT) && \
		cd ../docker-flexible-environment || exit 1; \
	done

# ---------- HTTPS Tunnel (required for MP notifications) ----------

tunnel:
	@if [ -f $(TUNNEL_PID) ] && kill -0 $$(cat $(TUNNEL_PID)) 2>/dev/null; then \
		echo "[mp-dev] Tunnel already running (PID $$(cat $(TUNNEL_PID)))"; \
		$(MAKE) tunnel-url; \
		exit 0; \
	fi
	@echo "[mp-dev] Requesting sudo access to update /etc/hosts (needed for VPN DNS bypass)..."
	@sudo -v
ifeq ($(TUNNEL),ngrok)
	@command -v ngrok >/dev/null 2>&1 || { echo "[mp-dev] ERROR: ngrok not installed. Run: brew install ngrok"; exit 1; }
	@if [ -n "$(NGROK_DOMAIN)" ]; then \
		echo "[mp-dev] Starting ngrok tunnel with static domain: $(NGROK_DOMAIN)"; \
		ngrok http $(PORT) --url=$(NGROK_DOMAIN) --log=stdout > .tunnel.log 2>&1 & echo $$! > $(TUNNEL_PID); \
	else \
		echo "[mp-dev] Starting ngrok tunnel (random URL — set NGROK_DOMAIN in .env for a fixed one)..."; \
		ngrok http $(PORT) --log=stdout > .tunnel.log 2>&1 & echo $$! > $(TUNNEL_PID); \
	fi
	@sleep 3
	@TUNNEL_URL=$$(curl -s http://127.0.0.1:4040/api/tunnels 2>/dev/null | grep -o '"public_url":"https://[^"]*' | grep -o 'https://[^"]*' | head -1); \
	if [ -z "$$TUNNEL_URL" ]; then \
		echo "[mp-dev] ERROR: Could not get tunnel URL. Check: cat .tunnel.log"; \
		cat .tunnel.log | tail -5; \
		exit 1; \
	fi; \
	echo "$$TUNNEL_URL" > .tunnel-url; \
	$(WP) option update siteurl "$$TUNNEL_URL"; \
	$(WP) option update home "$$TUNNEL_URL"; \
	TUNNEL_HOST=$$(echo "$$TUNNEL_URL" | sed 's|https://||'); \
	TUNNEL_IP=$$(dig +short "$$TUNNEL_HOST" @1.1.1.1 2>/dev/null | head -1); \
	if [ -n "$$TUNNEL_IP" ]; then \
		sudo sed -i '' '/# mp-tunnel/d' /etc/hosts 2>/dev/null; \
		echo "[mp-dev] Updating /etc/hosts for VPN DNS bypass..."; \
		sudo sh -c "printf '\n$$TUNNEL_IP $$TUNNEL_HOST  # mp-tunnel\n' >> /etc/hosts" \
			&& echo "[mp-dev] /etc/hosts updated" \
			|| { echo "[mp-dev] WARN: Could not update /etc/hosts. Run manually:"; \
			     echo "  sudo sh -c \"printf '\\\\n$$TUNNEL_IP $$TUNNEL_HOST  # mp-tunnel\\\\n' >> /etc/hosts\""; }; \
	fi; \
	echo ""; \
	echo "============================================"; \
	echo "  HTTPS Store: $$TUNNEL_URL/shop"; \
	echo "  HTTPS Admin: $$TUNNEL_URL/wp-admin"; \
	echo "  Webhook URL: $$TUNNEL_URL/?wc-api=WC_WooMercadoPago_*"; \
	echo "  Credentials: admin / admin"; \
	echo "============================================"; \
	echo ""; \
	echo "  Configure this URL in Mercado Pago webhook settings."; \
	echo "  The tunnel stays open until you run: make tunnel-stop"
else
	@command -v cloudflared >/dev/null 2>&1 || { echo "[mp-dev] ERROR: cloudflared not installed. Run: brew install cloudflared"; exit 1; }
	@echo "[mp-dev] Starting cloudflared tunnel to localhost:$(PORT)..."
	@cloudflared tunnel --url http://localhost:$(PORT) > .tunnel.log 2>&1 & echo $$! > $(TUNNEL_PID)
	@sleep 5
	@TUNNEL_URL=$$(grep -o 'https://[a-z0-9-]*\.trycloudflare\.com' .tunnel.log | head -1); \
	if [ -z "$$TUNNEL_URL" ]; then \
		echo "[mp-dev] ERROR: Could not get tunnel URL. Check: cat .tunnel.log"; \
		exit 1; \
	fi; \
	echo "$$TUNNEL_URL" > .tunnel-url; \
	$(WP) option update siteurl "$$TUNNEL_URL"; \
	$(WP) option update home "$$TUNNEL_URL"; \
	TUNNEL_HOST=$$(echo "$$TUNNEL_URL" | sed 's|https://||'); \
	TUNNEL_IP=$$(dig +short "$$TUNNEL_HOST" @1.1.1.1 2>/dev/null | head -1); \
	if [ -n "$$TUNNEL_IP" ]; then \
		sudo sed -i '' '/# mp-tunnel/d' /etc/hosts 2>/dev/null; \
		echo "[mp-dev] Updating /etc/hosts for VPN DNS bypass..."; \
		sudo sh -c "printf '\n$$TUNNEL_IP $$TUNNEL_HOST  # mp-tunnel\n' >> /etc/hosts" \
			&& echo "[mp-dev] /etc/hosts updated" \
			|| { echo "[mp-dev] WARN: Could not update /etc/hosts. Run manually:"; \
			     echo "  sudo sh -c \"printf '\\\\n$$TUNNEL_IP $$TUNNEL_HOST  # mp-tunnel\\\\n' >> /etc/hosts\""; }; \
	fi; \
	echo ""; \
	echo "============================================"; \
	echo "  HTTPS Store: $$TUNNEL_URL/shop"; \
	echo "  HTTPS Admin: $$TUNNEL_URL/wp-admin"; \
	echo "  Webhook URL: $$TUNNEL_URL/?wc-api=WC_WooMercadoPago_*"; \
	echo "  Credentials: admin / admin"; \
	echo "============================================"; \
	echo ""; \
	echo "  Configure this URL in Mercado Pago webhook settings."; \
	echo "  The tunnel stays open until you run: make tunnel-stop"
endif

tunnel-stop:
	@if [ -f $(TUNNEL_PID) ]; then \
		kill $$(cat $(TUNNEL_PID)) 2>/dev/null || true; \
		rm -f $(TUNNEL_PID) .tunnel.log; \
	fi
	@pkill -f "ngrok http" 2>/dev/null || true
	@pkill -f "cloudflared tunnel" 2>/dev/null || true
	@echo "[mp-dev] Tunnel stopped."
	@if grep -q "# mp-tunnel" /etc/hosts 2>/dev/null; then \
		echo "[mp-dev] Cleaning /etc/hosts..."; \
		sudo -v 2>/dev/null && sudo sed -i '' '/# mp-tunnel/d' /etc/hosts \
			&& echo "[mp-dev] /etc/hosts cleaned." \
			|| echo "[mp-dev] WARN: Could not clean /etc/hosts. Run manually: sudo sed -i '' '/# mp-tunnel/d' /etc/hosts"; \
	fi
	@if [ -f .tunnel-url ]; then \
		echo "[mp-dev] Restoring local URL..."; \
		$(WP) option update siteurl "http://localhost:$(PORT)" 2>/dev/null || true; \
		$(WP) option update home "http://localhost:$(PORT)" 2>/dev/null || true; \
		rm -f .tunnel-url; \
	fi

tunnel-url:
	@if [ -f .tunnel-url ]; then \
		echo "Tunnel URL: $$(cat .tunnel-url)"; \
	else \
		echo "No tunnel active. Run: make tunnel"; \
	fi

# ---------- Git info ----------

git-info:
	@echo "=== Git Remote ==="
	@docker exec $(CONTAINER) bash -c "cd /woocommerce-mercadopago && git remote -v" 2>/dev/null || echo "git not available in container"
	@echo ""
	@echo "=== Branch ==="
	@docker exec $(CONTAINER) bash -c "cd /woocommerce-mercadopago && git branch --show-current" 2>/dev/null || true
	@echo ""
	@echo "=== Status ==="
	@docker exec $(CONTAINER) bash -c "cd /woocommerce-mercadopago && git status --short" 2>/dev/null || true
	@echo ""
	@echo "Reminder: work from the HOST, not inside the container."
	@echo "The plugin dir is a volume mount — edits on host reflect instantly."

# ---------- Xdebug (PHP step debugging) ----------

debug-on:
	@docker exec $(CONTAINER) bash -c "sed -i 's/xdebug.mode=.*/xdebug.mode=debug/' /usr/local/etc/php/conf.d/xdebug.ini && apache2ctl graceful"
	@echo "[mp-dev] Xdebug ENABLED (mode=debug, port=9003)"
	@echo "  1. Open VS Code → Run and Debug → 'Listen for Xdebug (Docker)'"
	@echo "  2. Set breakpoints in PHP files"
	@echo "  3. Hit any page in the browser — debugger will pause"
	@echo "  4. Run 'make debug-off' when done (Xdebug slows PHP ~50%)"

debug-off:
	@docker exec $(CONTAINER) bash -c "sed -i 's/xdebug.mode=.*/xdebug.mode=off/' /usr/local/etc/php/conf.d/xdebug.ini && apache2ctl graceful"
	@echo "[mp-dev] Xdebug DISABLED (full performance restored)"

debug-status:
	@docker exec $(CONTAINER) php -r "echo 'Xdebug: ' . (extension_loaded('xdebug') ? 'loaded' : 'NOT loaded') . PHP_EOL . 'Mode: ' . ini_get('xdebug.mode') . PHP_EOL . 'Client: ' . ini_get('xdebug.client_host') . ':' . ini_get('xdebug.client_port') . PHP_EOL;"

# ---------- Plugin build ----------

build:
	docker exec -it $(CONTAINER) sh -c " \
		cd /woocommerce-mercadopago && \
		npm install && \
		composer install && \
		npm run build \
	"
