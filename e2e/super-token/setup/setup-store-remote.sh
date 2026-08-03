#!/usr/bin/env bash
# ============================================================================
# Preparação NEUTRA de uma loja EXTERNA (homologação AWS) para o E2E de Super Token.
#
# Contraparte do setup-store.sh para quando a loja NÃO é o docker local + túnel
# (cloudflare/ngrok bloqueados no Mac corporativo — ver run-e2e.sh e a memória do projeto).
# A loja externa já vem com suas próprias credenciais MP e siteurl configurados; aqui só
# aplicamos o que os CENÁRIOS de teste exigem, de forma idempotente:
#   • cupom `super-token-test` (fixed_cart, R$1) — cenário de reset por mudança de valor
#   • Fluid Checkout instalado e DESATIVADO       — cenário de resiliência (o teste ativa/desativa)
#
# NÃO toca em credenciais MP, siteurl/home nem no allow-list do seller — a loja já está pronta.
#
# Roda WP-CLI real no container remoto via SSH + `docker exec wp-<site>` (mesmo mecanismo do
# docker-flexible-environment/deploy/deploy.sh).
#
# Uso:  ./setup/setup-store-remote.sh <container-site> [instance]
#       ./setup/setup-store-remote.sh prod                 # instância do deploy/.deploy.env
#       ./setup/setup-store-remote.sh mlb skhalil-mg2-26   # instância explícita
#
#   <container-site>  sufixo do container remoto `wp-<site>` (ex.: `prod` p/ skhalil-prod).
#                     É o site da LOJA (container), não o país do teste do Super Token.
#   [instance]        nome/ FQDN da instância AWS. Default: HOMOLOG_INSTANCE do deploy/.deploy.env.
# ============================================================================
set -euo pipefail

SITE="${1:-}"; [ -n "$SITE" ] || { echo "uso: $0 <container-site> [instance]  (ex.: $0 prod)"; exit 1; }
# $SITE vira parte do nome do container (wp-$SITE) e é interpolado no comando remoto via ssh.
# Restringe a um slug para impedir injeção de shell na instância (ex.: 'prod; curl ...').
[[ "$SITE" =~ ^[a-z0-9-]+$ ]] || { echo "!! container-site inválido: '$SITE' (permitido: a-z, 0-9, hífen)"; exit 1; }
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REPO_ROOT="$(cd "$HERE/../.." && pwd)"
DEPLOY_DIR="$REPO_ROOT/docker-flexible-environment/deploy"

# Reaproveita a config de acesso do tooling de deploy (instância, chave, base domain).
[ -f "$DEPLOY_DIR/.deploy.env" ] && set -a && . "$DEPLOY_DIR/.deploy.env" && set +a || true
BASE_DOMAIN="${HOMOLOG_BASE_DOMAIN:-ppolimpo.io}"
INSTANCE_NAME="${2:-${HOMOLOG_INSTANCE:-}}"
[ -n "$INSTANCE_NAME" ] || { echo "!! instância não definida. Passe como 2º arg ou defina HOMOLOG_INSTANCE em $DEPLOY_DIR/.deploy.env"; exit 1; }
# FQDN já pronto vira host direto; nome curto ganha o base domain (idem deploy.sh).
[[ "$INSTANCE_NAME" == *"."* ]] && INSTANCE_HOST="$INSTANCE_NAME" || INSTANCE_HOST="${INSTANCE_NAME}.${BASE_DOMAIN}"

# Domínio público da loja = <prefix>-<site>.<base> (idem deploy.sh: SUB_PREFIX = HOMOLOG_PREFIX ou $SMOOTH_USER).
STORE_PREFIX="${HOMOLOG_PREFIX:-${SMOOTH_USER:-}}"
STORE_URL=""
[ -n "$STORE_PREFIX" ] && STORE_URL="https://${STORE_PREFIX}-${SITE}.${BASE_DOMAIN}"

SSH_USER="${HOMOLOG_SSH_USER:-ubuntu}"
SSH_KEY="${HOMOLOG_SSH_KEY:-$HOME/.ssh/id_aws}"
SSH_OPTS=(-i "$SSH_KEY" -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ServerAliveInterval=30 -o ConnectTimeout=15)
CONTAINER="wp-$SITE"

log()    { printf '\033[1;34m>>\033[0m %s\n' "$*"; }
# Silencia o aviso post-quantum do OpenSSH (ruído, não é erro) — idem deploy.sh.
filter() { grep -Ev "post-quantum|store now|may need|openssh.com|^\*\*" || true; }
# Escapa uma string para um único argumento entre aspas simples do shell (aspas internas inclusas).
sq() { local s=${1//\'/\'\\\'\'}; printf "'%s'" "$s"; }
# WP-CLI no container remoto. Cada token (container + args) é single-quoted antes de ir pro shell
# remoto via ssh, para o shell da instância não reinterpretar metacaracteres/expansões dos args.
remote_wp() {
  local cmd="docker exec $(sq "$CONTAINER") wp --allow-root"
  local a; for a in "$@"; do cmd+=" $(sq "$a")"; done
  ssh "${SSH_OPTS[@]}" "$SSH_USER@$INSTANCE_HOST" "$cmd" 2>&1 | filter
}

log "loja externa: container '$CONTAINER' na instância '$INSTANCE_NAME' ($INSTANCE_HOST)"
# Sanidade: o container existe e o wp-cli responde? Falha cedo com mensagem clara.
VER="$(remote_wp core version | tail -1)"
[ -n "$VER" ] || { echo "!! não consegui rodar wp-cli em '$CONTAINER' (container no ar? SSH ok?)"; exit 1; }
log "WooCommerce/WP acessível (wp core version: $VER)"

# 1. Cupom de teste p/ o cenário de reset por mudança de valor --------------------
log "garantindo cupom de teste 'super-token-test'..."
if remote_wp wc shop_coupon list --code=super-token-test --field=id --user=1 | grep -q '[0-9]'; then
  echo "   cupom já existe — ok"
else
  remote_wp wc shop_coupon create --code=super-token-test --discount_type=fixed_cart --amount=1 --user=1 >/dev/null \
    && echo "   cupom criado ✓"
fi

# 2. Fluid Checkout (instalado, DESATIVADO) p/ o cenário de resiliência ------------
# Instalado desativado; o teste de resiliência ativa só durante a execução e desativa depois.
log "garantindo Fluid Checkout instalado (desativado)..."
if remote_wp plugin is-installed fluid-checkout >/dev/null 2>&1; then
  echo "   já instalado"
else
  remote_wp plugin install fluid-checkout >/dev/null 2>&1 \
    || { echo "!! falha ao instalar fluid-checkout (o container alcança wp.org? tem escrita no FS de plugins?)"; exit 1; }
  echo "   instalado ✓"
fi
remote_wp plugin deactivate fluid-checkout >/dev/null 2>&1 || true
echo "   fluid-checkout desativado."

if [ -n "$STORE_URL" ]; then
  log "loja externa pronta: $STORE_URL/shop (container $CONTAINER)"
  echo "Próximo: SHOP_URL=$STORE_URL make test SITE=<país>"
else
  log "loja externa pronta (container $CONTAINER)."
  echo "Próximo: SHOP_URL=https://<prefix>-${SITE}.${BASE_DOMAIN} make test SITE=<país>  (defina HOMOLOG_PREFIX/SMOOTH_USER p/ montar a URL)"
fi
