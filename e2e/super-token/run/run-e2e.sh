#!/usr/bin/env bash
# ============================================================================
# Fase B — Execução do E2E de Super Token (repetível, automatizado).
# Boota o golden device (snapshot), garante loja+túnel no ar, expõe o DevTools do Chrome
# do emulador em :9333 (adb forward) e roda os testes Playwright daquele país.
#
# Uso:  ./run/run-e2e.sh <site>      (ex.: mlb)
#       GROUP=reset ./run/run-e2e.sh mlb       (só um grupo)
#       GREP="3G" ./run/run-e2e.sh mlb         (filtra por título)
#       N=1 ./run/run-e2e.sh mlb               (só o teste #1; ver `make list`)
# Pré: ./setup/setup-device.sh <site> e ./setup/setup-store.sh <site> já rodados.
# ============================================================================
set -euo pipefail

SITE="${1:-}"; [ -n "$SITE" ] || { echo "uso: $0 <site> (ex.: mlb)"; exit 1; }
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONFIG="$HERE/config/countries.json"
REPO_ROOT="$(cd "$HERE/../.." && pwd)"
DOCKER_DIR="$REPO_ROOT/docker-flexible-environment"
[ -f "$CONFIG" ] || { echo "!! crie $CONFIG a partir de config/countries.example.json"; exit 1; }

cfg() { python3 -c "import json,sys;print(json.load(open(sys.argv[1]))[sys.argv[2]].get(sys.argv[3],''))" "$CONFIG" "$SITE" "$1"; }
AVD="$(cfg avdName)"; PRODUCT="$(cfg productId)"; SNAPSHOT="golden"
# Filtros opcionais (do Makefile): GROUP = arquivo de um grupo; GREP = trecho do título; N = #teste.
GROUP="${GROUP:-}"; GREP="${GREP:-}"; N="${N:-}"

# N tem precedência: resolve o número no regex de --grep do teste ANTES de bootar o device
# (assim um N inválido falha rápido, sem subir o emulador). list-tests.sh é a fonte da numeração.
if [ -n "$N" ]; then
  RESOLVED_GREP="$("$HERE/run/list-tests.sh" "$SITE" --resolve "$N")" || exit 1
  [ -z "$GROUP$GREP" ] || echo ">> N=$N tem precedência — ignorando GROUP/GREP."
  GROUP=""; GREP="$RESOLVED_GREP"
fi

export ANDROID_HOME="${ANDROID_HOME:-$HOME/Library/Android/sdk}"
export PATH="$ANDROID_HOME/platform-tools:$ANDROID_HOME/emulator:$ANDROID_HOME/cmdline-tools/latest/bin:$PATH"
log() { printf '\033[1;34m>>\033[0m %s\n' "$*"; }

# 1. Loja precisa estar alcançável pelo Chrome do emulador ----------------------
# Duas fontes de loja:
#   • SHOP_URL no ambiente → loja EXTERNA pública (ex.: homologação em ppolimpo.io). O emulador
#     alcança direto pela internet — sem túnel (cloudflare/ngrok bloqueados no Mac corporativo).
#     Pula a guarda de túnel e o realinhamento do siteurl do docker local (a loja externa
#     gerencia o próprio siteurl). Uso: SHOP_URL=https://skhalil-prod.ppolimpo.io make test SITE=mlb
#   • sem SHOP_URL → loja docker local exposta pelo túnel que o `make store` gravou.
if [ -n "${SHOP_URL:-}" ]; then
  # SHOP_URL vira a baseURL do Chrome do emulador; sem esquema (typo comum: esquecer o https://) o
  # navegador falha com erro críptico. Exige http(s):// explícito, igual à validação de slug do STORE.
  [[ "$SHOP_URL" =~ ^https?:// ]] || { echo "!! SHOP_URL deve começar com http:// ou https:// (recebido: '$SHOP_URL')"; exit 1; }
  STORE_URL="${SHOP_URL%/}"   # sem barra final: navegamos para $STORE_URL/?add-to-cart=...
  EXTERNAL_STORE=1
  # Sinaliza ao helpers/store.js que estamos em loja externa: sem alvo remoto (WP_SSH), os cenários
  # `env` devem SKIPAR — nunca cair no `docker exec mp-wc-dev` local (mutaria a loja errada enquanto
  # o emulador testa a externa). Ver storeToolingAvailable() em helpers/store.js.
  export WP_EXTERNAL_STORE=1
  log "loja EXTERNA: $STORE_URL (SHOP_URL — sem túnel)"
  # Tooling de loja remota (helpers/store.js): os cenários `env` (Fluid Checkout, termos Classic)
  # precisam de WP-CLI na MESMA loja externa. Se STORE for passado, resolve o container wp-<STORE>
  # + a instância (deploy/.deploy.env, igual ao setup-store-remote.sh) e exporta WP_SSH/WP_CONTAINER;
  # sem STORE, esses cenários fazem skip (não sabemos onde rodar o WP-CLI).
  if [ -n "${STORE:-}" ]; then
    # $STORE vira wp-$STORE e é interpolado no comando remoto via ssh (helpers/store.js) — restringe
    # a um slug para impedir injeção de shell na instância.
    [[ "$STORE" =~ ^[a-z0-9-]+$ ]] || { echo "!! STORE inválido: '$STORE' (permitido: a-z, 0-9, hífen)"; exit 1; }
    DEPLOY_ENV="$DOCKER_DIR/deploy/.deploy.env"
    [ -f "$DEPLOY_ENV" ] && { set -a; . "$DEPLOY_ENV"; set +a; }
    ST_INSTANCE="${INSTANCE:-${HOMOLOG_INSTANCE:-}}"
    ST_BASE_DOMAIN="${HOMOLOG_BASE_DOMAIN:-ppolimpo.io}"
    if [ -n "$ST_INSTANCE" ]; then
      [[ "$ST_INSTANCE" == *"."* ]] && ST_HOST="$ST_INSTANCE" || ST_HOST="${ST_INSTANCE}.${ST_BASE_DOMAIN}"
      export WP_CONTAINER="wp-$STORE"
      export WP_SSH="${HOMOLOG_SSH_USER:-ubuntu}@$ST_HOST"
      export WP_SSH_KEY="${HOMOLOG_SSH_KEY:-$HOME/.ssh/id_aws}"
      log "tooling de loja remota: $WP_SSH → docker exec $WP_CONTAINER"
    else
      log "STORE=$STORE mas instância indefinida (HOMOLOG_INSTANCE/INSTANCE) — cenários env farão skip."
    fi
  fi
else
  EXTERNAL_STORE=0
  # Checa o PROCESSO do túnel + a URL — NÃO o DNS do host (a VPN bloqueia o trycloudflare
  # no host; quem precisa alcançar é o emulador, que resolve por conta própria).
  STORE_URL="$(cat "$DOCKER_DIR/.tunnel-url" 2>/dev/null || true)"
  TUNNEL_PID="$(cat "$DOCKER_DIR/.tunnel.pid" 2>/dev/null || true)"
  if [ -z "$STORE_URL" ] || ! { [ -n "$TUNNEL_PID" ] && kill -0 "$TUNNEL_PID" 2>/dev/null; }; then
    echo "!! túnel não está no ar. Rode antes: make store SITE=$SITE (ou defina SHOP_URL p/ loja externa)"; exit 1
  fi
  if curl -sL "$STORE_URL/shop" -o /dev/null --max-time 8 2>/dev/null; then
    log "loja: $STORE_URL (host alcança)"
  else
    log "loja: $STORE_URL (host não resolve — normal sob VPN; o emulador alcança)"
  fi
fi

# 1.1 Invariante túnel ↔ WP siteurl (só no modo túnel/docker local) -------------
# O flow navega pelo .tunnel-url (exportado como SHOP_URL abaixo), mas o WooCommerce faz
# canonical-redirect para o `siteurl`. Se divergirem, o add-to-cart cai num host e o /checkout/ é
# avaliado noutro → o carrinho fica vazio → o WC manda pro /cart/ e o teste "não redireciona pro
# checkout". O túnel já foi confirmado vivo acima, então re-alinhamos siteurl/home/_mp_custom_domain
# (idempotente, os mesmos updates do setup-store) em vez de abortar — zero atrito pro dev.
# Loja EXTERNA (SHOP_URL) gerencia o próprio siteurl e não roda no container mp-wc-dev — pula.
if [ "$EXTERNAL_STORE" = "0" ]; then
  WP_CONTAINER="${WP_CONTAINER:-mp-wc-dev}"
  wp_opt() { docker exec "$WP_CONTAINER" wp "$@" --allow-root 2>/dev/null | tr -d '\r'; }
  CURRENT_SITEURL="$(wp_opt option get siteurl || true)"
  if [ -z "$CURRENT_SITEURL" ]; then
    log "não consegui ler o siteurl (container '$WP_CONTAINER' no ar?) — seguindo sem checar o invariante."
  elif [ "$CURRENT_SITEURL" != "$STORE_URL" ]; then
    log "siteurl ($CURRENT_SITEURL) ≠ túnel ($STORE_URL) — re-alinhando WP para evitar redirect pro carrinho..."
    for opt in siteurl home _mp_custom_domain; do
      wp_opt option update "$opt" "$STORE_URL" >/dev/null || true
    done
    wp_opt cache flush >/dev/null 2>&1 || true
  fi
fi

# 2. Boot do golden device (snapshot) -------------------------------------------
# Valida QUAL AVD está no ar — não basta "existe algum emulador". Como há 1 AVD por país,
# rodar 'mlb' e depois 'mla' sem fechar o mlb rodaria o cenário do MLA contra o device do MLB.
RUNNING_AVD="$(adb emu avd name 2>/dev/null | head -1 | tr -d '\r' || true)"
if [ "$RUNNING_AVD" = "$AVD" ]; then
  log "golden '$AVD' já está no ar — reusando."
else
  # RUNNING_AVD vazio NÃO significa "sem emulador": `adb emu avd name` depende do token de auth do
  # console e volta vazio com frequência. `adb devices` é confiável. Se HÁ qualquer emulador no ar,
  # derruba antes de bootar — subir uma 2ª instância sobre o mesmo AVD causa conflito ("Running
  # multiple emulators with the same AVD") que reseta o device e mata o forward :9333.
  if adb devices | grep -q 'emulator-'; then
    log "emulador no ar (avd='${RUNNING_AVD:-desconhecido}') — desligando antes de bootar '$AVD'..."
    adb emu kill >/dev/null 2>&1 || true
    sleep 2
    pkill -f "emulator.*-avd $AVD" 2>/dev/null || true  # fallback: adb emu kill depende do token e pode falhar
    for _ in $(seq 1 30); do adb devices | grep -q 'emulator-' || break; sleep 1; done
  fi
  log "bootando '$AVD' a partir do snapshot '$SNAPSHOT'..."
  nohup emulator -avd "$AVD" -snapshot "$SNAPSHOT" -no-snapshot-save > "/tmp/emulator-run-$AVD.log" 2>&1 &
  adb wait-for-device
  until [ "$(adb shell getprop sys.boot_completed 2>/dev/null | tr -d '\r')" = "1" ]; do sleep 3; done
fi
log "device pronto ($AVD)."

# 2.1 Desbloqueio da tela (best-effort) ----------------------------------------
# Se a biometria foi cadastrada (make biometrics), há um PIN de fallback e a tela inicia
# bloqueada. Acorda, dispensa o keyguard e, se houver PIN (1234), digita. Sem PIN, é no-op.
adb shell input keyevent KEYCODE_WAKEUP >/dev/null 2>&1 || true
adb shell wm dismiss-keyguard >/dev/null 2>&1 || true
adb shell input swipe 540 1600 540 600 >/dev/null 2>&1 || true
adb shell input text 1234 >/dev/null 2>&1 || true
adb shell input keyevent 66 >/dev/null 2>&1 || true

# Mantém a tela acordada durante todo o run. A biometria exige PIN lock; se a tela apagar no meio
# de um fluxo longo (ex.: teste de cancelar+reabrir+aprovar) o keyguard re-engata, o Chrome cai
# pra background e o Android o mata → "Target page/browser has been closed" + cascata de ECONNREFUSED.
# stayon true impede o screen-off enquanto "plugado" (o emulador está sempre plugado).
adb shell svc power stayon true >/dev/null 2>&1 || true
adb shell settings put system screen_off_timeout 1800000 >/dev/null 2>&1 || true

# 3. Abre o checkout no Chrome e expõe o DevTools -------------------------------
# Reinicia o Chrome antes de abrir: o processo resumido do snapshot 'golden' às vezes deixa o
# DevTools travado — o WS conecta mas o connectOverCDP não conclui a enumeração de targets e
# estoura no setup, fazendo TODOS os testes falharem com "connectOverCDP: Timeout". Um Chrome
# recém-iniciado responde o CDP normalmente.
adb shell am force-stop com.android.chrome >/dev/null 2>&1 || true
sleep 2
adb shell am start -a android.intent.action.VIEW -d "$STORE_URL/?add-to-cart=$PRODUCT" \
  -n com.android.chrome/com.google.android.apps.chrome.Main >/dev/null 2>&1 \
  || { echo "!! falha ao abrir o Chrome no emulador (device conectado? com.android.chrome instalado?)"; exit 1; }
sleep 5
adb forward --remove tcp:9333 2>/dev/null || true
adb forward tcp:9333 localabstract:chrome_devtools_remote >/dev/null
# Aguarda o Chrome inicializar o DevTools (pode demorar após Payment Request ou boot pesado).
for _ in $(seq 1 20); do
  curl -s --max-time 2 http://localhost:9333/json/version >/dev/null 2>&1 && break
  sleep 1
done
log "CDP em :9333"

# 4. Testes Playwright (connectOverCDP no Chrome do emulador) -------------------
export NVM_DIR="$HOME/.nvm"
if [ -s "$NVM_DIR/nvm.sh" ]; then
  . "$NVM_DIR/nvm.sh"
  nvm use 22 >/dev/null 2>&1 || echo "[aviso] Node 22 não disponível via nvm — usando $(node -v)" >&2
fi
export SHOP_URL="$STORE_URL"

# Roda só a pasta do país no ar; N/GREP filtram por título (--grep), GROUP foca um grupo.
TARGET="tests/$SITE"
[ -n "$GROUP" ] && TARGET="tests/$SITE/$GROUP.spec.js"
PW_ARGS=("$TARGET")
[ -n "$GREP" ] && PW_ARGS+=("--grep" "$GREP")
log "playwright: $TARGET ${N:+(N=$N) }${GREP:+(grep: $GREP)}"
( cd "$HERE" && npx playwright test "${PW_ARGS[@]}" )
