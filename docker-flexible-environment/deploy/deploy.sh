#!/usr/bin/env bash
# ============================================================================
# Publica o plugin WooCommerce Mercado Pago do estado LOCAL numa instância AWS
# de homologação (gerenciada pelo smooth), 1 loja por país atrás de um Caddy com
# HTTPS (Let's Encrypt). Mesmo entrypoint/setup-store do ambiente local → paridade.
#
# Por dev: os domínios usam o SEU usuário de rede -> <SMOOTH_USER>-<site>.<base>.
# Cada dev aponta a SUA instância (descoberta via `smooth get-my-instances`).
#
# Uso:  ./deploy.sh <comando> [site]
#   publish <site>   sincroniza o código local e sobe/atualiza a loja do país
#   publish-all      (re)publica todas as lojas já ativas
#   sync             só re-envia o código local e reinicia as lojas ativas (rápido)
#   config K V       define uma constante do wp-config em TODAS as lojas ativas
#                    (ex: config MP_SDK_ENV beta  -> SDK JS de beta em todos os ambientes)
#   status | logs <site> | shell <site> | down <site> | destroy <site>
#
# Pré-requisitos: `smooth` configurado (SMOOTH_USER + chave no ssh-agent) e ~/.ssh/id_aws.
# Configure a instância uma vez:  echo 'HOMOLOG_INSTANCE=<nome>' >> deploy/.deploy.env
# ============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"   # raiz do repo (2 níveis acima de deploy/)

# Config local do dev (gitignored), se existir — define HOMOLOG_INSTANCE, etc.
[ -f "$SCRIPT_DIR/.deploy.env" ] && . "$SCRIPT_DIR/.deploy.env"

# smooth é pré-requisito (gerencia as instâncias e fornece o usuário de rede)
command -v smooth >/dev/null 2>&1 || { echo "!! 'smooth' não encontrado — é pré-requisito. Veja a doc do smooth (Confluence PLU)."; exit 1; }
SMOOTH_USER="${SMOOTH_USER:-}"
[ -n "$SMOOTH_USER" ] || { echo "!! defina SMOOTH_USER (export SMOOTH_USER=<seu-usuario-de-rede>) — pré-requisito do smooth."; exit 1; }

BASE_DOMAIN="${HOMOLOG_BASE_DOMAIN:-ppolimpo.io}"
SUB_PREFIX="${HOMOLOG_PREFIX:-$SMOOTH_USER}"        # domínios: <prefix>-<site>.<base>
INSTANCE_NAME="${HOMOLOG_INSTANCE:-}"               # sem default — resolvido em require_instance()
SSH_USER="${HOMOLOG_SSH_USER:-ubuntu}"
SSH_KEY="${HOMOLOG_SSH_KEY:-$HOME/.ssh/id_aws}"
REMOTE_DIR="${HOMOLOG_REMOTE_DIR:-/home/ubuntu/woo-homolog}"
export PHP_VERSION="${PHP_VERSION:-7.4}"
export HOMOLOG_PREFIX="$SUB_PREFIX"                 # consumido pelo docker-compose
export HOMOLOG_BASE_DOMAIN="$BASE_DOMAIN"
VALID_SITES="mlb mla mlm mco mlc mlu mpe mlb-zerada"

SSH_OPTS=(-i "$SSH_KEY" -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ServerAliveInterval=30)
INSTANCE_HOST=""

filter()  { grep -Ev "post-quantum|store now|may need|openssh.com|^\*\*" || true; }
log()     { printf '\033[1;34m>>\033[0m %s\n' "$*"; }
die()     { printf '\033[1;31m!!\033[0m %s\n' "$*" >&2; exit 1; }
ssh_cmd() { ssh "${SSH_OPTS[@]}" "$SSH_USER@$INSTANCE_HOST" "$@"; }
site_domain() { echo "${SUB_PREFIX}-$1.${BASE_DOMAIN}"; }
validate_site() { case " $VALID_SITES " in *" $1 "*) ;; *) die "site inválido: '$1' (válidos: $VALID_SITES)";; esac; }
instance_id() { smooth get-my-instances 2>/dev/null | awk -v n="$INSTANCE_NAME" '$1 ~ /^i-/ && $4==n {print $1; exit}'; }

# Resolve a instância do dev. Sem HOMOLOG_INSTANCE definido, lista as dele e orienta.
require_instance() {
  if [ -z "$INSTANCE_NAME" ]; then
    {
      echo ""
      echo "HOMOLOG_INSTANCE não definido. Suas instâncias no smooth:"
      smooth get-my-instances 2>&1 | filter || true
      echo ""
      echo "Escolha uma existente (ou crie: 'smooth create-instance <nome>') e configure (uma vez):"
      echo "  echo 'HOMOLOG_INSTANCE=<nome>' >> $SCRIPT_DIR/.deploy.env"
    } >&2
    exit 1
  fi
  # Se o nome já contém ponto (FQDN), usa direto; senão, anexa o base domain
  [[ "$INSTANCE_NAME" == *"."* ]] && INSTANCE_HOST="$INSTANCE_NAME" || INSTANCE_HOST="${INSTANCE_NAME}.${BASE_DOMAIN}"
}

ensure_remote() {
  log "garantindo Docker na instância $INSTANCE_NAME..."
  ssh_cmd 'bash -s' <<'EOS' 2>&1 | filter
mkdir -p ~/woo-homolog
if ! command -v docker >/dev/null 2>&1; then curl -fsSL https://get.docker.com | sudo sh && sudo usermod -aG docker "$USER"; fi
# Libera portas 80/443 caso apache2 do sistema esteja ocupando-as
if sudo ss -tlnp 2>/dev/null | grep -qE ':80[^0-9]|:443[^0-9]' && sudo systemctl is-active --quiet apache2 2>/dev/null; then
  echo ">> parando apache2 (ocupa portas 80/443)..."
  sudo systemctl stop apache2 && sudo systemctl disable apache2
fi
EOS
}

sync_plugin() {
  log "enviando código local -> instância (tar-over-ssh)..."
  ( cd "$PLUGIN_ROOT" && COPYFILE_DISABLE=1 tar czf - \
      --exclude='.git' --exclude='node_modules' --exclude='*.log' --exclude='e2e/test-results' --exclude='e2e/playwright-report' . 2>/dev/null ) \
    | ssh_cmd "mkdir -p $REMOTE_DIR/woocommerce-mercadopago && tar --warning=no-unknown-keyword -xzf - -C $REMOTE_DIR/woocommerce-mercadopago" 2>&1 | filter
}

push_compose() { ssh_cmd "cat > $REMOTE_DIR/docker-compose.yml" < "$SCRIPT_DIR/docker-compose.homolog.yml"; }

render_caddyfile() {  # $1 = sites ativos
  { for s in $1; do printf '%s {\n    reverse_proxy wp-%s:80\n}\n' "$(site_domain "$s")" "$s"; done; } \
    | ssh_cmd "cat > $REMOTE_DIR/Caddyfile"
}

get_active() { ssh_cmd "cat $REMOTE_DIR/.active-sites 2>/dev/null" 2>/dev/null | filter | tr '\n' ' ' | xargs || true; }
set_active() { printf '%s\n' $1 | ssh_cmd "cat > $REMOTE_DIR/.active-sites"; }

compose_up() {  # $1 = sites ativos
  local profiles=""; for s in $1; do profiles="$profiles --profile $s"; done
  ssh_cmd "cd $REMOTE_DIR && HOMOLOG_PREFIX='$SUB_PREFIX' HOMOLOG_BASE_DOMAIN='$BASE_DOMAIN' PHP_VERSION='$PHP_VERSION' docker compose $profiles up -d --build --remove-orphans" 2>&1 | filter | tail -10
}

wait_ready() {  # $1 = site
  log "aguardando setup da loja $1..."
  ssh_cmd "for i in \$(seq 1 60); do docker exec wp-$1 test -f /var/www/html/.mp-store-installed 2>/dev/null && exit 0; sleep 6; done; exit 1" \
    && log "loja $1 pronta" || die "timeout no setup de $1 (veja: ./deploy.sh logs $1)"
}

# Graceful Caddy reload that SURFACES failures. A rejected config or an unready caddy
# container leaves the (possibly new) hostname on the previous routing and unreachable, so
# we retry a few times and fail loudly instead of masking the error with `|| true`. Uses a
# command substitution (not a pipe to `filter`) so the real exit code is checked — a piped
# `... | filter` always reports success because filter() ends in `|| true` under pipefail.
reload_caddy() {
  log "recarregando Caddy (graceful reload, sem restart)..."
  local i out
  for i in 1 2 3; do
    if out="$(ssh_cmd 'docker exec caddy caddy reload --config /etc/caddy/Caddyfile' 2>&1)"; then
      printf '%s\n' "$out" | filter
      log "Caddy recarregado."
      return 0
    fi
    log "reload do Caddy falhou (tentativa $i/3); retentando em 2s..."
    sleep 2
  done
  die "falha ao recarregar o Caddy após 3 tentativas (config rejeitada ou container indisponível) — o novo domínio pode estar inacessível. Detalhe: $out"
}

fix_urls() {  # $1 = site, $2 = domain
  log "apontando URLs públicas + webhook..."
  ssh_cmd "docker exec wp-$1 wp --allow-root option update siteurl https://$2 >/dev/null && \
           docker exec wp-$1 wp --allow-root option update home https://$2 >/dev/null && \
           docker exec wp-$1 wp --allow-root option update _mp_custom_domain https://$2 >/dev/null && \
           docker exec wp-$1 wp --allow-root rewrite flush --hard >/dev/null 2>&1" 2>&1 | filter
  reload_caddy
}

cmd_publish() {
  local site="${1:-}"; [ -n "$site" ] || die "uso: ./deploy.sh publish <site>"
  validate_site "$site"
  local skip_sync="${2:-}"   # publish-all sincroniza uma vez antes do loop -> passa "skip-sync"
  local id; id="$(instance_id)"; [ -n "$id" ] || die "instância '$INSTANCE_NAME' não encontrada no smooth (rode 'smooth get-my-instances')"
  local dom; dom="$(site_domain "$site")"
  log "publicando '$site' em https://$dom (instância $INSTANCE_NAME / $id)"

  if [ "$skip_sync" != "skip-sync" ]; then ensure_remote; sync_plugin; fi

  log "apontando domínio $dom -> instância..."
  smooth add-domain "$id" "${SUB_PREFIX}-${site}" 2>&1 | filter

  local active; active="$(get_active)"
  case " $active " in *" $site "*) ;; *) active="$(echo "$active $site" | xargs)";; esac
  set_active "$active"

  push_compose
  render_caddyfile "$active"
  log "build + up (lojas ativas: $active)..."
  compose_up "$active"
  wait_ready "$site"
  fix_urls "$site" "$dom"

  printf '\033[1;32m== pronto ==\033[0m\n  Loja:  https://%s/shop\n  Admin: https://%s/wp-admin (admin/admin)\n' "$dom" "$dom"
}

cmd_publish_all() {
  local a; a="$(get_active)"; [ -n "$a" ] || die "nenhuma loja ativa ainda (use: publish <site>)"
  ensure_remote
  sync_plugin   # uma única transferência -> o volume remoto é compartilhado entre as lojas
  for s in $a; do cmd_publish "$s" skip-sync; done
}
cmd_sync() {
  sync_plugin
  local a; a="$(get_active)"
  log "reiniciando lojas ativas: $a"
  for s in $a; do
    if ssh_cmd "docker inspect wp-$s >/dev/null 2>&1"; then
      ssh_cmd "docker restart wp-$s >/dev/null 2>&1" 2>&1 | filter
    else
      log "container wp-$s não encontrado em .active-sites, pulando"
    fi
  done
  log "código republicado em: $a"
}
cmd_status()  { log "instância: $INSTANCE_NAME | lojas ativas: $(get_active)"; ssh_cmd "cd $REMOTE_DIR && docker compose ps 2>/dev/null" 2>&1 | filter; for s in $(get_active); do echo "  https://$(site_domain "$s")/shop"; done; }
cmd_logs()    { local s="${1:?uso: logs <site>}"; validate_site "$s"; ssh_cmd "docker logs -f --tail=120 wp-$s"; }
cmd_shell()   { local s="${1:?uso: shell <site>}"; validate_site "$s"; ssh "${SSH_OPTS[@]}" -t "$SSH_USER@$INSTANCE_HOST" "docker exec -it wp-$s bash"; }
cmd_down()    { local s="${1:?uso: down <site>}"; validate_site "$s"; ssh_cmd "docker stop wp-$s" 2>&1 | filter; }
cmd_config() {
  local key="${1:?uso: config <CONSTANTE> <valor> (ex: MP_SDK_ENV beta)}"
  local val="${2:?uso: config <CONSTANTE> <valor>}"
  local active; active="$(get_active)"; [ -n "$active" ] || die "nenhuma loja ativa (use: publish <site>)"
  local key_q val_q; key_q="$(printf '%q' "$key")"; val_q="$(printf '%q' "$val")"   # escapa p/ o shell remoto (evita injection via valor com aspas/&&)
  for s in $active; do
    log "wp-config em $s: define('$key', '$val')"
    ssh_cmd "docker exec wp-$s wp --allow-root config set $key_q $val_q --type=constant && docker exec wp-$s wp --allow-root cache flush >/dev/null 2>&1" 2>&1 | filter
  done
  log "aplicado em: $active"
}
cmd_prepare_zip() {
  local site="${1:-}"; [ -n "$site" ] || die "uso: ./deploy.sh prepare-zip <site>"
  validate_site "$site"
  log "preparando wp-$site para upload de zip via wp-admin..."
  ssh_cmd "
    docker exec wp-$site rm -f /var/www/html/wp-content/plugins/woocommerce-mercadopago 2>/dev/null || true
    docker exec wp-$site wp --allow-root --skip-plugins eval '
      \$plugins = get_option(\"active_plugins\", []);
      \$filtered = array_values(array_filter(\$plugins, function(\$p) {
        return strpos(\$p, \"woocommerce-mercadopago\") === false;
      }));
      update_option(\"active_plugins\", \$filtered);
      echo \"Plugin desativado. Ativos restantes: \" . count(\$filtered) . PHP_EOL;
    ' 2>/dev/null || true
    docker exec wp-$site wp --allow-root config set FS_METHOD direct --type=constant 2>/dev/null || true
    docker exec wp-$site bash -c 'mkdir -p /var/www/html/wp-content/uploads/\$(date +%Y/%m) /var/www/html/wp-content/upgrade && chown -R www-data:www-data /var/www/html/wp-content/uploads /var/www/html/wp-content/upgrade'
  " 2>&1 | filter
  printf '\033[1;32m== pronto ==\033[0m\n  Acesse: https://%s/wp-admin/plugin-install.php\n  Faça upload do zip e ative o plugin.\n' "$(site_domain "$site")"
}

cmd_reload_caddy() {
  reload_caddy
}

cmd_destroy() {
  local s="${1:?uso: destroy <site>}"; validate_site "$s"
  ssh_cmd "cd $REMOTE_DIR && docker rm -f wp-$s 2>/dev/null; docker volume rm woo-homolog_wp-$s-data woo-homolog_db-$s-data 2>/dev/null" 2>&1 | filter
  set_active "$(echo "$(get_active)" | tr ' ' '\n' | grep -v "^$s$" | tr '\n' ' ' | xargs || true)"
  log "loja $s removida"
}

cmd="${1:-}"
case "$cmd" in
  publish|publish-all|sync|status|logs|shell|down|destroy|config|prepare-zip|reload-caddy) require_instance ;;
  *) echo "uso: $0 {publish <site>|publish-all|sync|config <CONST> <valor>|status|logs <site>|shell <site>|down <site>|destroy <site>|prepare-zip <site>|reload-caddy}"; exit 1 ;;
esac

case "$cmd" in
  publish)      cmd_publish "${2:-}";;
  publish-all)  cmd_publish_all;;
  sync)         cmd_sync;;
  status)       cmd_status;;
  logs)         cmd_logs "${2:-}";;
  shell)        cmd_shell "${2:-}";;
  down)         cmd_down "${2:-}";;
  destroy)      cmd_destroy "${2:-}";;
  config)       cmd_config "${2:-}" "${3:-}";;
  prepare-zip)  cmd_prepare_zip "${2:-}";;
  reload-caddy) cmd_reload_caddy;;
esac
