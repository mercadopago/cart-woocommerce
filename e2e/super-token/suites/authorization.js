import { test, expect } from "../fixtures.js";
import {
  startCheckoutReadyToPay,
  reopenCheckout,
  selectFirstSavedCard,
  fillSecurityCode,
  placeOrder,
  expectSuperTokenVisible,
  expectOrderReceived,
  expectIdentityError,
  expectMetric,
  PENDING_BUYER,
} from "../flows/super-token.js";

const { buyerFor } = require("../data/country.js");
const { skipIfNotSite } = require("../../helpers/site-guard.js");
const { approveBiometrics, cancelBiometrics, resetMpApp } = require("../helpers/device.js");

const TEST_CVV = "123";

// The MP app opens for biometric authorization after place order. The helpers wait for the
// app to be in the foreground before sending the finger touch or BACK, so timing is reliable.
// Prerequisite: at least one fingerprint enrolled in the emulator (Extended Controls →
// Fingerprint). Run `make biometrics SITE=<country>` once to set up PIN + fingerprint.

export function authorizationScenarios(site) {
  const buyer = buyerFor(site);

  test.describe(`Super Token authorization — ${site.toUpperCase()}`, () => {
    test.beforeEach(() => {
      skipIfNotSite(test, site.toUpperCase());
      test.skip(!buyer.email, PENDING_BUYER);
    });

    test("Given an eligible buyer with a saved card, When they approve the biometric prompt, Then the order is completed", async ({ page }) => {
      await startCheckoutReadyToPay(page, buyer);
      await expectSuperTokenVisible(page);
      await selectFirstSavedCard(page);
      await fillSecurityCode(page, TEST_CVV);

      await placeOrder(page);
      const approved = await approveBiometrics(buyer.fingerId);
      expect(approved, `biometric approval timed out — is fingerId=${buyer.fingerId} enrolled? check countries.json`).toBeTruthy();

      await expectOrderReceived(page);
    });

    test("Given a buyer who placed the order, When they cancel the biometric prompt, Then the order is not completed, the saved cards remain and an authorize error metric is sent", async ({ page }) => {
      await resetMpApp(); // clear the previous auth session so the biometric prompt appears again
      await startCheckoutReadyToPay(page, buyer);
      await expectSuperTokenVisible(page);
      await selectFirstSavedCard(page);
      await fillSecurityCode(page, TEST_CVV);

      // Cancelar a biometria faz authorizePayment rejeitar (USER_CANCELLED) → métrica de erro de
      // autorização. Timeout maior: a métrica só sai depois do fluxo lento (place order → app MP
      // abre → BACK), que passa de 20s. (Funde a antiga métrica por fault, infazível via injeção.)
      const authError = expectMetric(page, "error_to_authorize_payment", 40000);
      await placeOrder(page);
      await cancelBiometrics(); // waits for the MP app, presses BACK
      await authError;

      await expectIdentityError(page);
      await expect(page).not.toHaveURL(/order-received/);
    });

    test("Given a cancelled authorization, When the buyer retries, Then the order is completed on the second attempt", async ({ page }) => {
      test.setTimeout(150000); // 2 fluxos de biometria + reabrir + re-disparo do email do ST (até 3x por checkout)
      await resetMpApp();
      await startCheckoutReadyToPay(page, buyer);
      await expectSuperTokenVisible(page);
      await selectFirstSavedCard(page);
      await fillSecurityCode(page, TEST_CVV);

      await placeOrder(page);
      await cancelBiometrics(); // first attempt: cancel
      await expectIdentityError(page);

      // Second attempt on a fresh checkout — re-arms the ST authorization cleanly (after a cancel
      // the same-page click tokenizes the CVV but never reopens the MP app).
      await reopenCheckout(page, buyer);
      await expectSuperTokenVisible(page);
      await selectFirstSavedCard(page);
      await fillSecurityCode(page, TEST_CVV);
      await placeOrder(page);
      const approved = await approveBiometrics(buyer.fingerId);
      expect(approved, "biometric approval timed out on retry").toBeTruthy();

      await expectOrderReceived(page);
    });
  });
}
