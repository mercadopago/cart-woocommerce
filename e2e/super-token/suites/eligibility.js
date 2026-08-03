import { test, expect } from "../fixtures.js";
import { SELECTORS } from "../selectors.js";
import {
  startCustomCheckout,
  expectSuperTokenVisible,
  expectCustomCheckoutWithoutSuperToken,
  expectMetric,
  notEnrolledEmail,
  PENDING_BUYER,
} from "../flows/super-token.js";

// Detecção de app / elegibilidade do MP. Bloqueá-la = "app não detectado" → sem ST.
// Cobre os dois bundles: `op-pay/prapi` (prod/v1) e `/v2/user-flows` (homol) — RegExp p/ não
// acoplar o teste a um bundle específico (o env é escolhido em super-token-loader.js).
const APP_DETECTION = /op-pay\/prapi|\/v2\/user-flows/;

const { buyerFor } = require("../data/country.js");
const { skipIfNotSite } = require("../../helpers/site-guard.js");

// Cenários de elegibilidade do Super Token, compartilhados entre os países.
// Cada país chama eligibilityScenarios("mlb") em tests/<país>/eligibility.spec.js.
// Quando surgir diferença por país, mova o cenário divergente para o arquivo do país.
export function eligibilityScenarios(site) {
  const buyer = buyerFor(site);

  test.describe(`Super Token eligibility — ${site.toUpperCase()}`, () => {
    test.beforeEach(() => skipIfNotSite(test, site.toUpperCase()));

    test("Given a Super Token-eligible buyer, When they open the Custom Checkout, Then the saved cards are displayed and the eligibility metric is sent", async ({ page }) => {
      test.skip(!buyer.email, PENDING_BUYER);

      const eligibilityMetric = expectMetric(page, "can_use_super_token");
      await startCustomCheckout(page, buyer);

      await expectSuperTokenVisible(page);
      await expect(page.locator(SELECTORS.savedCard).first()).toBeVisible();
      await eligibilityMetric;
    });

    test("Given a buyer not eligible for Super Token, When they open the Custom Checkout, Then it falls back to the standard checkout and a build-authenticator error metric is sent", async ({ page }) => {
      test.skip(!buyer.email, PENDING_BUYER);
      // Email que não é uma conta MP → o SDK não consegue construir o authenticator →
      // `error_to_build_authenticator` + cai no checkout padrão (funde a antiga métrica por fault).
      const buildError = expectMetric(page, "error_to_build_authenticator");
      await startCustomCheckout(page, { ...buyer, email: notEnrolledEmail(buyer.email) });
      await buildError;

      await expectCustomCheckoutWithoutSuperToken(page);
    });

    test("Given a device without the Super Token app, When they open the Custom Checkout, Then it falls back to the standard checkout", async ({ page, faults }) => {
      // Bloqueia a detecção de app/elegibilidade → ApplicationsDetect falha → sem ST.
      await faults.failUrl(APP_DETECTION);

      await startCustomCheckout(page, buyer);

      await expectCustomCheckoutWithoutSuperToken(page);
    });
  });
}
