// @ts-check
const { defineConfig } = require("@playwright/test");

const path = require("path");
require("dotenv").config(); // super-token/.env (se existir)
// O buyer_data.js (documentos, endereços por país) vive em e2e/ e lê do e2e/.env. dotenv não
// sobrescreve vars já definidas → o .env local (se houver) vence; o de e2e/ preenche o resto.
// EXCEÇÃO: o SHOP_URL do e2e/.env é o docker LOCAL (localhost:8080), inalcançável pelo emulador —
// a loja do super-token vem do túnel (.tunnel-url via shop.js). Não importamos esse SHOP_URL.
const hadShopUrl = "SHOP_URL" in process.env;
require("dotenv").config({ path: path.resolve(__dirname, "../.env") });
if (!hadShopUrl) delete process.env.SHOP_URL;

// Testes E2E do Super Token: dirigem o Chrome do emulador (golden device) via CDP.
// O run-e2e.sh prepara device + loja/túnel e expõe o DevTools em :9333 (adb forward).
// A URL da loja vem de SHOP_URL/.tunnel-url (ver data/shop.js) — os flows navegam com URL
// absoluta porque o connectOverCDP reusa o contexto do Chrome (baseURL do Playwright não se aplica).
module.exports = defineConfig({
  testDir: "./tests",
  // 75s é o teto do caminho de SUCESSO (autorização: app MP + biometria). A falha rápida vem dos
  // timeouts por AÇÃO abaixo — sem actionTimeout, um .fill()/.click() que trava espera o teste
  // inteiro (parecia "travado por 90s").
  timeout: 75000,
  fullyParallel: false,
  workers: 1,
  // retries: 1 mesmo local — o contexto do Chrome é compartilhado e o ST carrega async (espera o SDK);
  // no run completo há corridas de timing (cold start) que a 2ª tentativa (já aquecida) resolve.
  retries: 1,
  reporter: [["list"], ["html", { open: "never" }]],
  use: {
    trace: "on-first-retry",
    // Falha rápido: uma ação (fill/click/check) que não consegue agir cai em 15s em vez de pendurar
    // até o timeout do teste; navegação (goto da loja via túnel) tem 30s.
    actionTimeout: 15000,
    navigationTimeout: 30000,
  },
});
