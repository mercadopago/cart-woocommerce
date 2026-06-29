import { test, expect } from "../fixtures.js";
import { SELECTORS } from "../selectors.js";
import {
  startCheckoutReadyToPay,
  openCheckout,
  selectFirstSavedCard,
  fillBuyerEmail,
  placeOrder,
  expectSuperTokenVisible,
  PENDING_BUYER,
} from "../flows/super-token.js";

const { buyerFor } = require("../data/country.js");
const { skipIfNotSite } = require("../../helpers/site-guard.js");
const { storeToolingAvailable, enableClassicCheckoutWithTerms, restoreBlocksCheckout } = require("../helpers/store.js");

export function validationScenarios(site) {
  const buyer = buyerFor(site);

  test.describe(`Super Token validation — ${site.toUpperCase()}`, () => {
    test.beforeEach(() => skipIfNotSite(test, site.toUpperCase()));

    test("Given a saved card without CVV/installments, When the buyer places the order, Then it is blocked without reloading", async ({ page }) => {
      test.skip(!buyer.email, PENDING_BUYER);
      await startCheckoutReadyToPay(page, buyer);
      await expectSuperTokenVisible(page);

      await selectFirstSavedCard(page);
      await placeOrder(page);

      await expect(page).not.toHaveURL(/order-received/);
    });

    test("Given a required field is empty, When the buyer places the order, Then the MP app is not opened", async ({ page }) => {
      test.skip(!buyer.email, PENDING_BUYER);
      await startCheckoutReadyToPay(page, buyer);
      await expectSuperTokenVisible(page);

      await selectFirstSavedCard(page);
      await fillBuyerEmail(page, "");
      await placeOrder(page);

      await expect(page).not.toHaveURL(/order-received/);
      await expect(page.locator(SELECTORS.authorizedPseudotoken)).toHaveValue("");
    });

    // Cenário CLASSIC: o checkbox de termos obrigatório (#terms) só existe no checkout Classic — no
    // Blocks o bloco de termos é só texto (consentimento implícito), sem checkbox (comprovado via CDP).
    // Por isso o teste TROCA a loja para checkout Classic + termos durante a execução (via WP-CLI) e
    // restaura o Blocks no finally. Faz skip se o WP-CLI/loja docker não estiver disponível.
    test("Given a required terms checkbox (Classic checkout), When it is unchecked, Then the order is blocked", async ({ page }) => {
      test.skip(!storeToolingAvailable(), "WP-CLI/docker store unavailable");

      try {
        enableClassicCheckoutWithTerms();
        await openCheckout(page, buyer);

        const terms = page.locator(SELECTORS.terms);
        await expect(terms).toBeVisible({ timeout: 15000 });
        await terms.uncheck();

        await page.locator("#place_order").click();

        // Bloqueado pela validação de termos (client-side) — erro exibido e sem ir para o pedido.
        await expect(page.locator(".woocommerce-error")).toBeVisible({ timeout: 10000 });
        await expect(page).not.toHaveURL(/order-received/);
      } finally {
        restoreBlocksCheckout();
      }
    });
  });
}
