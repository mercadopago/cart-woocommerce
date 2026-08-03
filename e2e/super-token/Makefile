# ============================================================================
# Super Token E2E — atalhos. Veja README.md e docs/ para detalhes.
#
#   make setup SITE=mlb                  configura device (golden) + biometria (PIN 1234) + loja/túnel do país (1x)
#   make list  SITE=mlb                  lista numerada dos cenários (sem bootar o device)
#   make test  SITE=mlb                  roda os cenários E2E no device daquele país
#   make test  SITE=mlb N=1              roda só o teste de número 1 (ver make list)
#   make test  SITE=mlb GROUP=reset      roda só um grupo
#   make test  SITE=mlb GREP="3G"        filtra por trecho do título do teste
#
# Sites: mlb mla mlm  (precisam existir em config/countries.json)
# Grupos: eligibility · authorization · reset · validation · resilience
# ============================================================================
SITE ?=
GROUP ?=
GREP ?=
N ?=
STORE ?=
INSTANCE ?=
SHELL := /bin/bash

.DEFAULT_GOAL := help
.PHONY: help setup setup-device biometrics store store-remote list test status clean require-site require-store

help:
	@echo ""
	@echo "Super Token E2E — comandos:"
	@echo "  make setup SITE=<país>              device (golden) + biometria (PIN 1234) + loja/túnel + allow-list (1x por país)"
	@echo "  make list  SITE=<país>              lista numerada dos cenários (sem device)"
	@echo "  make test  SITE=<país>              roda os cenários E2E no device do país"
	@echo "  make test  SITE=<país> N=<número>   roda só um teste (número da 'make list')"
	@echo "  make test  SITE=<país> GROUP=<nome> roda só um grupo"
	@echo "  make test  SITE=<país> GREP=<texto> filtra por trecho do título do teste"
	@echo ""
	@echo "  make setup-device SITE=..  só o device (AVD + login Google/MP + snapshot)"
	@echo "  make biometrics   SITE=..  cadastra PIN 1234 + digital no golden (já incluso no setup; obrigatório p/ o grupo authorization)"
	@echo "  make store        SITE=..  só a loja local + túnel (re-rodar quando o túnel cair)"
	@echo "  make store-remote STORE=.. prepara loja EXTERNA (cupom + Fluid Checkout) via SSH — sem túnel"
	@echo "                             ex.: make store-remote STORE=prod"
	@echo "                             depois: STORE=prod SHOP_URL=https://<prefix>-prod.ppolimpo.io make test SITE=mlb"
	@echo "                             (passe STORE no test tb: habilita os cenários env — Fluid Checkout/termos — na loja remota)"
	@echo "  make status                AVDs, device conectado e URL do túnel"
	@echo "  make clean                 derruba o túnel cloudflared"
	@echo ""
	@echo "Grupos: eligibility · authorization · reset · validation · resilience"
	@echo "Sites válidos: definidos em config/countries.json"
	@echo ""

# Configuração completa de um país (primeira vez): device + biometria + loja.
# biometrics entra aqui porque sem PIN 1234 + digital no golden o grupo `authorization` falha
# (o teste autoriza o pagamento via `adb emu finger touch`). É 1x por device e tem passos guiados.
setup: require-site setup-device biometrics store
	@echo ">> setup de '$(SITE)' completo. Rode: make test SITE=$(SITE)"

setup-device: require-site
	@./setup/setup-device.sh $(SITE)

biometrics: require-site
	@./setup/setup-biometrics.sh $(SITE)

store: require-site
	@./setup/setup-store.sh $(SITE)

# Loja EXTERNA (homologação AWS): prepara cupom + Fluid Checkout via SSH, sem túnel.
# STORE é o sufixo do container remoto wp-<STORE> (ex.: prod), não o país do teste.
# INSTANCE opcional (default: HOMOLOG_INSTANCE do deploy/.deploy.env).
store-remote: require-store
	@./setup/setup-store-remote.sh $(STORE) $(INSTANCE)

list: require-site
	@./run/list-tests.sh $(SITE)

test: require-site
	@GROUP="$(GROUP)" GREP="$(GREP)" N="$(N)" STORE="$(STORE)" INSTANCE="$(INSTANCE)" ./run/run-e2e.sh $(SITE)

status:
	@echo "== AVDs ==";        $(ANDROID_HOME)/emulator/emulator -list-avds 2>/dev/null || emulator -list-avds 2>/dev/null || true
	@echo "== device ==";      adb devices | grep emulator || echo "  (nenhum emulador rodando)"
	@echo "== túnel ==";       cat ../../docker-flexible-environment/.tunnel-url 2>/dev/null || echo "  (sem túnel — rode make store)"

clean:
	@kill $$(cat ../../docker-flexible-environment/.tunnel.pid 2>/dev/null) 2>/dev/null && echo ">> túnel derrubado" || echo ">> nenhum túnel ativo"

require-site:
	@[ -n "$(SITE)" ] || { echo "!! defina SITE (ex.: make $(MAKECMDGOALS) SITE=mlb)"; exit 1; }
	@[ -f config/countries.json ] || { echo "!! crie config/countries.json a partir de config/countries.example.json"; exit 1; }

require-store:
	@[ -n "$(STORE)" ] || { echo "!! defina STORE (sufixo do container wp-<STORE>, ex.: make store-remote STORE=prod)"; exit 1; }
