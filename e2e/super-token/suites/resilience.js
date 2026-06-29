import { test, expect } from "../fixtures.js";
import { SELECTORS } from "../selectors.js";
import { startCustomCheckout, openCheckout, expectSuperTokenVisible, PENDING_BUYER } from "../flows/super-token.js";

const { buyerFor } = require("../data/country.js");
const { skipIfNotSite } = require("../../helpers/site-guard.js");
const { storeToolingAvailable, isPluginInstalled, activatePlugin, deactivatePlugin } = require("../helpers/store.js");

// Plugin de checkout de terceiro compatível com Blocks (transforma em multistep). Instalado
// (desativado) pela Fase A (setup-store.sh); este teste ativa só durante a execução e desativa.
const THIRD_PARTY_PLUGIN = "fluid-checkout";

export function resilienceScenarios(site) {
  const buyer = buyerFor(site);

  test.describe(`Super Token resilience — ${site.toUpperCase()}`, () => {
    test.beforeEach(() => skipIfNotSite(test, site.toUpperCase()));

    test("Given a third-party checkout plugin (Fluid Checkout), When the buyer opens the checkout, Then the Mercado Pago checkout still integrates", async ({ page }) => {
      test.skip(
        !storeToolingAvailable() || !isPluginInstalled(THIRD_PARTY_PLUGIN),
        "fluid-checkout not installed (run make setup) or WP-CLI unavailable",
      );

      try {
        activatePlugin(THIRD_PARTY_PLUGIN);
        await openCheckout(page, buyer);

        // O Fluid Checkout está mesmo ativo (vira multistep, adiciona a classe no body)...
        await expect(page.locator("body.has-fluid-checkout")).toBeAttached();
        // ...e o checkout do Mercado Pago segue integrado no fluxo (o radio existe no DOM, ainda
        // que oculto no passo não-atual do multistep).
        await expect(page.locator(SELECTORS.customCheckoutRadio).first()).toHaveCount(1);
      } finally {
        deactivatePlugin(THIRD_PARTY_PLUGIN); // não pode vazar para os outros testes
      }
    });

    test("Given a 3G connection, When the buyer opens the Custom Checkout, Then the Super Token still loads", async ({ page, faults }) => {
      test.skip(!buyer.email, PENDING_BUYER);
      await faults.throttle3G();
      // Sob 3G a navegação demora bem mais que o navigationTimeout global (30s) → dá folga só aqui.
      page.setDefaultNavigationTimeout(90000);

      await startCustomCheckout(page, buyer);

      await expectSuperTokenVisible(page, 45000);
    });
  });
}
