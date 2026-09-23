# Atalhos da automação de homologação (wrapper do deploy.sh).
#   make publish SITE=mlb     publica/atualiza a loja brasileira em homologação
#   make publish SITE=mla     idem Argentina
#   make sync                 re-publica o código local nas lojas ativas (rápido)
#   make config KEY=MP_SDK_ENV VAL=beta   define constante do wp-config em TODAS as lojas
#   make status               estado das lojas + URLs
#   make logs SITE=mlb        logs da loja
#   make down SITE=mlb        para a loja (mantém dados)
#   make destroy SITE=mlb     remove a loja e os dados
.PHONY: publish publish-all sync config status logs shell down destroy prepare-zip reload-caddy \
	shared-help shared-check-site shared-config-check shared-secrets-check \
	shared-check-compose shared-artifacts-check shared-seed-artifacts \
	shared-secrets-check-all shared-up shared-recreate shared-status shared-live-check shared-down
SITE ?=
KEY ?=
VAL ?=
CANDIDATE_ARTIFACT ?=
CANDIDATE_VERSION ?=
BASELINE_ARTIFACT ?=
BASELINE_VERSION ?=

SHARED_SITE := $(strip \
	$(if $(filter MLA mla,$(SITE)),mla,\
	$(if $(filter MLB mlb,$(SITE)),mlb,\
	$(if $(filter MLC mlc,$(SITE)),mlc,\
	$(if $(filter MLM mlm,$(SITE)),mlm,\
	$(if $(filter MCO mco,$(SITE)),mco,\
	$(if $(filter MLU mlu,$(SITE)),mlu,\
	$(if $(filter MPE mpe,$(SITE)),mpe))))))))
DC := $(shell if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then echo "docker compose"; elif command -v docker-compose >/dev/null 2>&1; then echo "docker-compose"; else echo "ERROR"; fi)
SHARED_COMPOSE := $(DC) -f docker-compose.e2e-poc.yml
SHARED_SECRET_FILE := ./secrets/$(SHARED_SITE).env
SHARED_EXAMPLE_FILES := \
	E2E_SECRETS_MLA_FILE=./examples/credentials/mla.env.example \
	E2E_SECRETS_MLB_FILE=./examples/credentials/mlb.env.example \
	E2E_SECRETS_MLC_FILE=./examples/credentials/mlc.env.example \
	E2E_SECRETS_MLM_FILE=./examples/credentials/mlm.env.example \
	E2E_SECRETS_MCO_FILE=./examples/credentials/mco.env.example \
	E2E_SECRETS_MLU_FILE=./examples/credentials/mlu.env.example \
	E2E_SECRETS_MPE_FILE=./examples/credentials/mpe.env.example

publish:      ; @./deploy.sh publish $(SITE)
publish-all:  ; @./deploy.sh publish-all
sync:         ; @./deploy.sh sync
config:       ; @./deploy.sh config $(KEY) "$(VAL)"
status:       ; @./deploy.sh status
logs:         ; @./deploy.sh logs $(SITE)
shell:        ; @./deploy.sh shell $(SITE)
down:         ; @./deploy.sh down $(SITE)
destroy:      ; @./deploy.sh destroy $(SITE)
prepare-zip:  ; @./deploy.sh prepare-zip $(SITE)
reload-caddy: ; @./deploy.sh reload-caddy

shared-help:
	@printf '%s\n' \
		'Shared E2E infrastructure (run on big-shared):' \
		'  make shared-config-check                Validate all 14 services without real secrets' \
		'  make shared-secrets-check SITE=MLA      Validate one real country secret file' \
		'  make shared-seed-artifacts ...          Seed RC/staging and production/homol once' \
		'  make shared-secrets-check-all           Require valid files for all seven countries' \
		'  make shared-up                          Build and start all seven staging/homol pairs' \
		'  make shared-recreate SITE=MLA           Apply rotated credentials to one pair' \
		'  make shared-status                      Show all shared stores' \
		'  make shared-live-check                  Probe public Caddy behavior on all 14 stores' \
		'  make shared-down                        Stop all stores and preserve their volumes'

shared-check-site:
	@if [ -z "$(SHARED_SITE)" ]; then \
		echo 'SITE must be one of: MLA MLB MLC MLM MCO MLU MPE.' >&2; \
		exit 2; \
	fi

shared-check-compose:
	@if [ "$(DC)" = "ERROR" ]; then echo 'Docker Compose is required.' >&2; exit 1; fi

shared-config-check: shared-check-compose
	@$(SHARED_EXAMPLE_FILES) $(SHARED_COMPOSE) config --quiet

shared-secrets-check: shared-check-site
	@./validate-shared-secrets.sh $(SHARED_SECRET_FILE)

shared-secrets-check-all:
	@for site in mla mlb mlc mlm mco mlu mpe; do \
		./validate-shared-secrets.sh "./secrets/$$site.env" || exit 1; \
	done

shared-artifacts-check:
	@node ../../e2e/bootstrap-shared-artifacts.js --check

shared-seed-artifacts:
	@if [ -z "$(CANDIDATE_ARTIFACT)" ] || [ -z "$(CANDIDATE_VERSION)" ] \
		|| [ -z "$(BASELINE_ARTIFACT)" ] || [ -z "$(BASELINE_VERSION)" ]; then \
		echo 'CANDIDATE_ARTIFACT, CANDIDATE_VERSION, BASELINE_ARTIFACT and BASELINE_VERSION are required.' >&2; \
		exit 2; \
	fi
	@node ../../e2e/bootstrap-shared-artifacts.js \
		--candidate-artifact "$(abspath $(CANDIDATE_ARTIFACT))" \
		--candidate-version "$(CANDIDATE_VERSION)" \
		--baseline-artifact "$(abspath $(BASELINE_ARTIFACT))" \
		--baseline-version "$(BASELINE_VERSION)"

shared-up: shared-config-check shared-secrets-check-all shared-artifacts-check
	@COMPOSE_PARALLEL_LIMIT=2 $(SHARED_COMPOSE) up -d --build --force-recreate caddy-e2e
	@for site in mla mlb mlc mlm mco mlu mpe; do \
		echo "Starting shared pair: $$site"; \
		COMPOSE_PARALLEL_LIMIT=2 $(SHARED_COMPOSE) up -d --build "wp-staging-$$site" "wp-homol-$$site" || exit 1; \
	done

shared-recreate: shared-check-compose shared-check-site shared-secrets-check shared-artifacts-check
	@$(SHARED_COMPOSE) up -d --force-recreate \
		wp-staging-$(SHARED_SITE) wp-homol-$(SHARED_SITE)

shared-status: shared-check-compose
	@$(SHARED_COMPOSE) ps

shared-live-check:
	@./test-shared-caddy-live.sh

shared-down: shared-check-compose
	@$(SHARED_COMPOSE) down
