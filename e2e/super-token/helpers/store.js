// Toggling da loja via WP-CLI (docker exec). Usado por cenários `env` que precisam ativar uma
// condição na loja durante o teste (plugin de checkout terceiro; checkout Classic + termos) e
// desfazê-la depois. Se o WP-CLI não estiver acessível, o teste faz skip.
//
// Dois modos, selecionados por env:
//   • LOCAL  (default): `docker exec ${WP_CONTAINER:-mp-wc-dev}` na máquina do dev (loja docker).
//   • REMOTO (WP_SSH definido): a mesma loja da homologação AWS que o run-e2e.sh testa via SHOP_URL.
//     Roteia por `ssh <WP_SSH> "docker exec <WP_CONTAINER> ..."` (mesmo mecanismo do
//     setup-store-remote.sh). O run-e2e.sh exporta WP_SSH + WP_CONTAINER no modo externo.
//
// IMPORTANTE: passar dados para o WP-CLI é feito via PIPE/REDIRECT do shell (`printf ... | docker
// exec -i`, `cat file | ...`), NÃO via execSync({input}) — esse último não entrega o stdin ao
// `docker exec -i` neste ambiente (comprovado). No modo remoto o produtor (printf/cat) fica LOCAL
// e alimenta o stdin do ssh, que o encaminha ao `docker exec -i` remoto.
const { execSync } = require("child_process");
const fs = require("fs");
const os = require("os");
const path = require("path");

const CONTAINER = process.env.WP_CONTAINER || "mp-wc-dev";
const REMOTE = process.env.WP_SSH || ""; // ex.: ubuntu@skhalil-mg2-26.ppolimpo.io
const SSH_KEY = process.env.WP_SSH_KEY || path.join(os.homedir(), ".ssh", "id_aws");
// Em loja externa (run-e2e.sh exporta WP_EXTERNAL_STORE) sem alvo remoto (WP_SSH), o fallback local
// (docker exec mp-wc-dev) mutaria a loja ERRADA enquanto o emulador testa a externa. Nesse caso o
// tooling é considerado indisponível → os cenários `env` skipam em vez de cair no docker local.
const LOCAL_FALLBACK_BLOCKED = process.env.WP_EXTERNAL_STORE === "1" && !REMOTE;
// Sufixo por container: a suíte roda workers:1, mas dois `make test` em terminais distintos
// apontando para containers diferentes não compartilham o mesmo backup do checkout Blocks.
const BACKUP_FILE = path.join(os.tmpdir(), `st-e2e-checkout-blocks-backup-${CONTAINER}.html`);

// Escapa uma string para um único argumento entre aspas simples do shell (inclusive aspas internas).
function sq(s) {
  return `'${String(s).replace(/'/g, "'\\''")}'`;
}

// Envolve um comando que roda NO HOST do docker (local ou remoto via ssh). O produtor de stdin,
// quando existe, é prependado FORA daqui para ficar local (ver os call sites com pipe).
function onHost(dockerCmd) {
  if (!REMOTE) return dockerCmd;
  return `ssh -i ${sq(SSH_KEY)} -o BatchMode=yes -o StrictHostKeyChecking=accept-new ${sq(REMOTE)} ${sq(dockerCmd)}`;
}

function sh(cmd) {
  // stderr em "pipe" (não "ignore"): numa falha SSH — timeout de conexão, host key rejeitada,
  // container parado — a mensagem do stderr entra na exceção (error.stderr), em vez de só
  // "Command failed: ssh ...". storeToolingAvailable() ainda cai no catch → skip, sem ruído.
  return execSync(cmd, { encoding: "utf-8", timeout: 60000, stdio: ["ignore", "pipe", "pipe"] });
}

function wp(args) {
  return sh(onHost(`docker exec ${CONTAINER} wp ${args} --allow-root`));
}

function storeToolingAvailable() {
  if (LOCAL_FALLBACK_BLOCKED) return false; // loja externa sem alvo remoto → não usa o docker local
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
  sh(`printf '%s' '[woocommerce_checkout]' | ${onHost(`docker exec -i ${CONTAINER} wp post update ${pid} - --allow-root`)}`);

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
      sh(`cat ${BACKUP_FILE} | ${onHost(`docker exec -i ${CONTAINER} wp post update ${pid} - --allow-root`)}`);
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
