# Atalhos da automação de homologação (wrapper do deploy.sh).
#   make publish SITE=mlb     publica/atualiza a loja brasileira em homologação
#   make publish SITE=mla     idem Argentina
#   make sync                 re-publica o código local nas lojas ativas (rápido)
#   make config KEY=MP_SDK_ENV VAL=beta   define constante do wp-config em TODAS as lojas
#   make status               estado das lojas + URLs
#   make logs SITE=mlb        logs da loja
#   make down SITE=mlb        para a loja (mantém dados)
#   make destroy SITE=mlb     remove a loja e os dados
.PHONY: publish publish-all sync config status logs shell down destroy prepare-zip reload-caddy
SITE ?=
KEY ?=
VAL ?=

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
