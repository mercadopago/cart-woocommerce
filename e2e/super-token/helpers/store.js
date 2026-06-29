// Toggling da loja local via WP-CLI (docker exec). Usado por cenários `env` que precisam ativar
// uma condição na loja durante o teste (plugin de checkout terceiro; checkout Classic + termos) e
// desfazê-la depois. Acopla à loja docker local (container mp-wc-dev); se o WP-CLI não existir, o
// teste faz skip.
//
// IMPORTANTE: passar dados para o WP-CLI é feito via PIPE/REDIRECT do shell (`printf ... | docker
// exec -i`, `cat file | ...`), NÃO via execSync({input}) — esse último não entrega o stdin ao
// `docker exec -i` neste ambiente (comprovado).
const { execSync } = require("child_process");
const fs = require("fs");
const os = require("os");
const path = require("path");

const CONTAINER = process.env.WP_CONTAINER || "mp-wc-dev";
// Sufixo por container: a suíte roda workers:1, mas dois `make test` em terminais distintos
// apontando para containers diferentes não compartilham o mesmo backup do checkout Blocks.
const BACKUP_FILE = path.join(os.tmpdir(), `st-e2e-checkout-blocks-backup-${CONTAINER}.html`);

function sh(cmd) {
  return execSync(cmd, { encoding: "utf-8", timeout: 60000, stdio: ["ignore", "pipe", "ignore"] });
}

function wp(args) {
  return sh(`docker exec ${CONTAINER} wp ${args} --allow-root`);
}

function storeToolingAvailable() {
  try {
    wp("--version");
    return true;
  } catch {
    return false;
  }
}

function isPluginInstalled(slug) {
  try {
    wp(`plugin is-installed ${slug}`);
    return true;
  } catch {
    return false;
  }
}

function activatePlugin(slug) {
  try {
    wp(`plugin activate ${slug}`);
    return true;
  } catch {
    return false;
  }
}

// Best-effort: usado no finally para garantir que o plugin não vaze para os outros testes.
function deactivatePlugin(slug) {
  try {
    wp(`plugin deactivate ${slug}`);
  } catch {
    /* teardown best-effort */
  }
}

function checkoutPageId() {
  return wp("option get woocommerce_checkout_page_id").trim();
}

// Troca o checkout para Classic ([woocommerce_checkout]) + liga termos obrigatório, guardando o
// conteúdo Blocks original num arquivo (sobrevive a crash do teste — só faz backup se ainda for
// Blocks, então um run que crashou em Classic não sobrescreve o backop bom). Idempotente; cria a
// página de termos se não existir.
function enableClassicCheckoutWithTerms() {
  const pid = checkoutPageId();
  const current = wp(`post get ${pid} --field=content`);
  if (!current.includes("[woocommerce_checkout]")) fs.writeFileSync(BACKUP_FILE, current);
  sh(`printf '%s' '[woocommerce_checkout]' | docker exec -i ${CONTAINER} wp post update ${pid} - --allow-root`);

  let tid = wp("post list --post_type=page --pagename=terms-and-conditions --field=ID --format=ids").trim();
  if (!tid) {
    tid = wp("post create --post_type=page --post_title='Terms and Conditions' --post_content='E2E terms.' --post_status=publish --porcelain").trim();
  }
  wp(`option update woocommerce_terms_page_id ${tid}`);
}

// Restaura o checkout Blocks original (do arquivo de backup) + desliga termos. Best-effort.
function restoreBlocksCheckout() {
  try {
    const pid = checkoutPageId();
    if (fs.existsSync(BACKUP_FILE)) {
      sh(`cat ${BACKUP_FILE} | docker exec -i ${CONTAINER} wp post update ${pid} - --allow-root`);
    }
    wp("option delete woocommerce_terms_page_id");
  } catch {
    /* teardown best-effort */
  }
}

module.exports = {
  storeToolingAvailable,
  isPluginInstalled,
  activatePlugin,
  deactivatePlugin,
  enableClassicCheckoutWithTerms,
  restoreBlocksCheckout,
};
