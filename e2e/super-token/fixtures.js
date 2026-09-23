import { test as base, chromium, expect } from "@playwright/test";

const CDP_ENDPOINT = process.env.CDP_ENDPOINT || "http://localhost:9333";

// Perfil "Slow 3G" (mesmos números do DevTools).
const NET_3G = { offline: false, latency: 400, downloadThroughput: 51200, uploadThroughput: 51200 };
const NET_DEFAULT = { offline: false, latency: 0, downloadThroughput: -1, uploadThroughput: -1 };

export const test = base.extend({
  // Conecta no Chrome do emulador (já aberto pelo run-e2e.sh) — uma vez por worker.
  cdpBrowser: [
    async ({}, use) => {
      // Retry once: after a Payment Request flow Chrome DevTools can take a moment to recover.
      let browser;
      for (let attempt = 1; attempt <= 2; attempt++) {
        try {
          browser = await chromium.connectOverCDP(CDP_ENDPOINT, { timeout: 20000 });
          break;
        } catch (e) {
          if (attempt === 2) throw e;
          await new Promise((r) => setTimeout(r, 5000));
        }
      }
      await use(browser);
      // Não fechar: browser.close() encerraria o Chrome do emulador e os próximos testes/worker
      // não conseguiriam reconectar. A conexão CDP é descartada quando o processo termina.
    },
    { scope: "worker" },
  ],

  page: async ({ cdpBrowser }, use) => {
    const context = cdpBrowser.contexts()[0];
    // A aba MAIS RECENTE é a que o run-e2e.sh abriu (a ativa no emulador). Dirigir essa e
    // trazê-la para frente alinha o fluxo, o prompt do app MP e o que o executor vê — e o
    // authorizePayment (Payment Request) só invoca o app numa aba em primeiro plano.
    const open = context.pages().filter((p) => !p.isClosed());
    const page = open[open.length - 1] || (await context.newPage());
    // Acorda a tela + dispensa o keyguard antes de qualquer navegação. Em runs longos a tela trava
    // (a biometria exige PIN lock) e o 1º goto do teste cai em "Target page/browser has been closed".
    require("./helpers/device.js").wakeAndUnlock();
    await page.bringToFront();
    // Coleta sinais do SDK no console para diagnosticar quando o ST não renderiza.
    page.__superTokenSignals = [];
    page.on("console", (msg) => {
      const text = msg.text();
      if (/enroll|simplified|super.?token|prapi|fast.?payment|one.?tap/i.test(text)) {
        page.__superTokenSignals.push(text.slice(0, 200));
      }
    });
    await use(page);
  },

  // Manipulações do ambiente que precisam ser desfeitas ao fim do teste — o contexto do
  // Chrome é compartilhado entre cenários, então nada pode vazar de um teste para o outro.
  faults: async ({ page }, use) => {
    const client = await page.context().newCDPSession(page);
    // Page.addScriptToEvaluateOnNewDocument is silently ignored on a fresh CDP session until the
    // Page domain is enabled: the script is never registered and the injection is a no-op (the
    // cookie side of forceVariant masked this because context.addCookies set it independently).
    // Enable it once so faults.inject actually runs in the page's main world before page scripts.
    await client.send("Page.enable");
    const injected = [];
    let throttled = false;

    await use({
      // Roda um script antes do SDK em toda navegação.
      async inject(source) {
        const { identifier } = await client.send("Page.addScriptToEvaluateOnNewDocument", { source });
        injected.push(identifier);
      },
      // Aborta (erro de rede) as requests que casam com o padrão.
      async failUrl(pattern) {
        await page.route(pattern, (route) => route.abort());
      },
      // Responde com status/body fixos as requests que casam com o padrão.
      async respondUrl(pattern, { status = 500, body = { error: "forced" } } = {}) {
        await page.route(pattern, (route) =>
          route.fulfill({ status, contentType: "application/json", body: JSON.stringify(body) }),
        );
      },
      // Emula rede 3G.
      async throttle3G() {
        throttled = true;
        await client.send("Network.emulateNetworkConditions", NET_3G);
      },
    });

    for (const identifier of injected) {
      await client.send("Page.removeScriptToEvaluateOnNewDocument", { identifier }).catch(() => {});
    }
    await page.unrouteAll({ behavior: "ignoreErrors" }).catch(() => {});
    if (throttled) await client.send("Network.emulateNetworkConditions", NET_DEFAULT).catch(() => {});
  },
});

export { expect };
