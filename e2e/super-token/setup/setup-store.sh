#!/usr/bin/env bash
# ============================================================================
# Fase A — Setup da loja local + túnel + seller no allow-list (por país).
# Sobe a loja do docker-flexible-environment, abre um túnel cloudflared (HTTPS
# público que o emulador alcança) e habilita o seller no Super Token.
#
# Uso:  ./setup/setup-store.sh <site>      (ex.: mlb)
# Requer: docker, cloudflared. Para o allow-list, precisa de VPN MeLi.
#   O JWT é obtido via `fury token`; se falhar (HTTP 000 = sem VPN), pule
#   o passo — o seller provavelmente já está no allow-list de sessões anteriores.
# ============================================================================
set -euo pipefail

SITE="${1:-}"; [ -n "$SITE" ] || { echo "uso: $0 <site> (ex.: mlb)"; exit 1; }
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONFIG="$HERE/config/countries.json"
REPO_ROOT="$(cd "$HERE/../.." && pwd)"
DOCKER_DIR="$REPO_ROOT/docker-flexible-environment"
wp() { docker exec mp-wc-dev wp --allow-root "$@"; }
[ -f "$CONFIG" ] || { echo "!! crie $CONFIG a partir de config/countries.example.json"; exit 1; }

cfg() { python3 -c "import json,sys;print(json.load(open(sys.argv[1]))[sys.argv[2]].get(sys.argv[3],''))" "$CONFIG" "$SITE" "$1"; }
cfg_set() { # cfg_set <key> <value>
  python3 - "$1" "$2" "$CONFIG" "$SITE" <<'PY'
import json, sys
key, val, path, site = sys.argv[1], sys.argv[2], sys.argv[3], sys.argv[4]
d = json.load(open(path))
d[site][key] = val
json.dump(d, open(path, 'w'), indent=2, ensure_ascii=False)
print()
PY
}
SELLER="$(cfg sellerAppId)"
BUYER_EMAIL="$(cfg buyerEmail)"

log() { printf '\033[1;34m>>\033[0m %s\n' "$*"; }

# Pedir email do comprador se ainda não configurado ---------------------------
if [ -z "$BUYER_EMAIL" ] || [[ "$BUYER_EMAIL" == TODO_* ]]; then
  echo ""
  printf '\033[1;33m  ✋\033[0m Email do comprador MP para \033[1m%s\033[0m não configurado.\n' "$SITE"
  printf '     (usuário de teste @testuser.com com cartão salvo — modo produção, mas conta de teste)\n'
  printf '  Email: '
  read -r BUYER_EMAIL _ || true
  if [ -z "$BUYER_EMAIL" ]; then
    echo "   !! email não informado — continuando sem email (configure countries.json manualmente)"
  else
    cfg_set buyerEmail "$BUYER_EMAIL"
    echo "   email '$BUYER_EMAIL' salvo em countries.json ✓"
  fi
fi

# 1. Injetar credenciais PROD do país no docker-flexible-environment/.env --------
# As credenciais ficam em e2e/.env (ex: MP_ACCESS_TOKEN_PROD_MLA).
# O Super Token EXIGE usuário de PRODUÇÃO (enrolled instruments não existem em sandbox),
# então a ausência das credenciais é erro FATAL: sem elas o ST nunca renderiza e o teste
# falharia muito mais à frente com um erro enganoso ("ST não renderizou"). Validamos aqui.
E2E_ENV="$REPO_ROOT/e2e/.env"
SITE_UPPER="$(echo "$SITE" | tr '[:lower:]' '[:upper:]')"
[ -f "$E2E_ENV" ] || { echo "!! $E2E_ENV não encontrado — necessário para as credenciais PROD do Super Token"; exit 1; }

PUB_KEY="$(grep "^MP_PUBLIC_KEY_PROD_${SITE_UPPER}=" "$E2E_ENV" | cut -d= -f2- | tr -d '"' || true)"
ACC_TOK="$(grep "^MP_ACCESS_TOKEN_PROD_${SITE_UPPER}=" "$E2E_ENV" | cut -d= -f2- | tr -d '"' || true)"
[ -n "$PUB_KEY" ] || { echo "!! MP_PUBLIC_KEY_PROD_${SITE_UPPER} não encontrado em $E2E_ENV (o Super Token exige credenciais de produção)"; exit 1; }
[ -n "$ACC_TOK" ] || { echo "!! MP_ACCESS_TOKEN_PROD_${SITE_UPPER} não encontrado em $E2E_ENV (o Super Token exige credenciais de produção)"; exit 1; }

log "credenciais PROD $SITE_UPPER detectadas em e2e/.env — injetando no docker .env..."
DOCKER_ENV="$DOCKER_DIR/.env"
# Arquivo temporário com nome aleatório (evita link-following/CWE-377). Criado no MESMO diretório
# do .env para que o `mv` seja atômico (mesmo filesystem) — o arquivo guarda credenciais PROD, então
# uma escrita parcial por crash entre filesystems não pode acontecer.
TMP_ENV="$(mktemp "$(dirname "$DOCKER_ENV")/.tmp.XXXXXX")"
grep -v "^MP_PUBLIC_KEY_PROD=\|^MP_ACCESS_TOKEN_PROD=" "$DOCKER_ENV" > "$TMP_ENV" \
  && mv "$TMP_ENV" "$DOCKER_ENV"
echo "MP_PUBLIC_KEY_PROD=$PUB_KEY" >> "$DOCKER_ENV"
echo "MP_ACCESS_TOKEN_PROD=$ACC_TOK" >> "$DOCKER_ENV"
echo "   credenciais PROD $SITE_UPPER aplicadas."

# 2. Loja local -----------------------------------------------------------------
log "subindo a loja local (SITE=$SITE)..."
( cd "$DOCKER_DIR" && make up SITE="$SITE" >/dev/null )

# 2. Túnel cloudflared (direto — evita o sudo /etc/hosts; o emulador resolve via DNS) ----
log "abrindo túnel cloudflared..."
cd "$DOCKER_DIR"
# Mata processos anteriores para evitar múltiplos tunnels simultâneos (causa DNS flakiness)
pkill -f "cloudflared tunnel" 2>/dev/null && sleep 2 || true
rm -f .tunnel.pid .tunnel.log .tunnel-url 2>/dev/null || true
cloudflared tunnel --url http://localhost:8080 > .tunnel.log 2>&1 &
echo $! > .tunnel.pid
TUNNEL_URL=""
for _ in $(seq 1 90); do
  TUNNEL_URL="$(grep -o 'https://[a-z0-9-]*\.trycloudflare\.com' .tunnel.log 2>/dev/null | head -1 || true)"
  CONNECTED="$(grep -c 'Registered tunnel connection' .tunnel.log 2>/dev/null || true)"
  [ -n "$TUNNEL_URL" ] && [ "${CONNECTED:-0}" -gt 0 ] && break
  sleep 1
done
[ -n "$TUNNEL_URL" ] || { echo "!! não consegui obter a URL do túnel (cat $DOCKER_DIR/.tunnel.log)"; exit 1; }
echo "$TUNNEL_URL" > .tunnel-url
log "túnel: $TUNNEL_URL (conexão registrada)"
# Pequena espera para DNS propagar no emulador
sleep 5

log "apontando WP siteurl/home/_mp_custom_domain para o túnel..."
wp option update siteurl "$TUNNEL_URL" >/dev/null
wp option update home "$TUNNEL_URL" >/dev/null
wp option update _mp_custom_domain "$TUNNEL_URL" >/dev/null

# Forçar credenciais do país — o make up preserva a instalação anterior (flag .mp-store-installed),
# então o setup interno do container pode não sobrescrever as credenciais da sessão anterior.
if [ -n "${PUB_KEY:-}" ] && [ -n "${ACC_TOK:-}" ]; then
  log "forçando credenciais PROD $SITE_UPPER no WP + desativando modo teste..."
  wp option update _mp_public_key_prod  "$PUB_KEY" >/dev/null && echo "   public_key_prod ✓"
  wp option update _mp_access_token_prod "$ACC_TOK" >/dev/null && echo "   access_token_prod ✓"
  wp option update _site_id_v1 "$SITE_UPPER" >/dev/null && echo "   site_id=$SITE_UPPER ✓"
  # Desativar modo teste: o plugin usa esta opção para decidir qual chave enviar ao SDK
  wp option update checkbox_checkout_test_mode "" >/dev/null 2>&1 || true
  wp cache flush >/dev/null 2>&1 || true
fi

# 3. Seller no allow-list do Super Token ----------------------------------------
if [ -n "$SELLER" ] && [ "$SELLER" != "TODO_SELLER_APP_ID" ]; then
  log "habilitando seller $SELLER no allow-list do Super Token..."
  JWT="$(fury token 2>/dev/null | sed 's/Bearer //' || true)"
  # Sem --location: é um POST interno para endpoint conhecido; seguir redirect reenviaria
  # corpo + Authorization para um host imprevisto. --max-time já cobre o timeout.
  CODE="$(curl -s -o /dev/null -w '%{http_code}' \
    --request POST 'https://mp-op-cho-account-data-api.melioffice.com/v1/internal/allow-list' \
    --header 'Content-Type: application/json' \
    --header "Authorization: Bearer $JWT" \
    --data "{\"client_ids\": [\"$SELLER\"]}" --max-time 15 || true)"
  case "$CODE" in
    200|201|204) echo "   allow-list: seller habilitado (HTTP $CODE)" ;;
    400|409)     echo "   allow-list: seller já habilitado (HTTP $CODE) — ok" ;;
    401|403)     echo "   allow-list: HTTP $CODE — token inválido. Rode: fury token" ;;
    000)         echo "   allow-list: sem conexão (HTTP 000). VPN da Meli ativa? Se o seller já foi"
                 echo "               habilitado antes, ignore — os testes funcionarão normalmente." ;;
    *)           echo "   allow-list: HTTP $CODE (inesperado)" ;;
  esac
else
  echo "   (sellerAppId não configurado — pulei o allow-list)"
fi

# 4. Plugin de checkout de terceiro (Fluid Checkout) p/ o cenário de resiliência ------
# Instalado DESATIVADO; o teste de resiliência ativa só durante a execução e desativa depois.
log "garantindo Fluid Checkout instalado (desativado) p/ o teste de resiliência..."
wp plugin is-installed fluid-checkout >/dev/null 2>&1 || wp plugin install fluid-checkout >/dev/null 2>&1 || true
wp plugin deactivate fluid-checkout >/dev/null 2>&1 || true
echo "   fluid-checkout instalado (desativado)."

# 5. Cupom de teste p/ o cenário de reset por mudança de valor --------------------
log "garantindo cupom de teste 'super-token-test' criado..."
wp wc shop_coupon list --code=super-token-test --field=id --user=1 2>/dev/null \
  | grep -q '[0-9]' \
  && echo "   cupom já existe — ok" \
  || wp wc shop_coupon create --code=super-token-test --discount_type=fixed_cart --amount=1 --user=1 \
     >/dev/null && echo "   cupom criado ✓"

log "loja pronta: $TUNNEL_URL/shop"
echo "Próximo: ./run/run-e2e.sh $SITE"
