import { test, expect } from "../fixtures.js";
import { SELECTORS } from "../selectors.js";
import {
  startCustomCheckout,
  startCheckoutReadyToPay,
  reopenCheckout,
  selectCustomCheckout,
  selectOtherPaymentMethod,
  selectFirstSavedCard,
  fillSecurityCode,
  placeOrder,
  applyCoupon,
  expectCheckoutLoading,
  expectSuperTokenVisible,
  expectCustomCheckoutWithoutSuperToken,
  expectMetric,
  recordMetrics,
  PENDING_BUYER,
} from "../flows/super-token.js";

const { buyerFor } = require("../data/country.js");
const { skipIfNotSite } = require("../../helpers/site-guard.js");

const RESET_COUPON = "super-token-test"; // criado pelo setup-store.sh
// Dados/cartões da conta MP. Cobre os dois bundles: `account-data` (prod/v1) e
// `account-payment-methods` (homol) — RegExp p/ não acoplar o teste a um bundle específico.
const ACCOUNT_DATA = /account-data|account-payment-methods/;
const ORDER_API = "**/wc/store/v1/checkout**";

export function resetScenarios(site) {
  const buyer = buyerFor(site);

  test.describe(`Super Token reset — ${site.toUpperCase()}`, () => {
    test.beforeEach(() => {
      skipIfNotSite(test, site.toUpperCase());
      test.skip(!buyer.email, PENDING_BUYER);
    });

    test("Given the saved cards are loaded, When the buyer switches payment method and returns, Then the cards are reused without re-running eligibility", async ({ page }) => {
      await startCustomCheckout(page, buyer);
      await expectSuperTokenVisible(page);

      const metrics = recordMetrics(page);
      const switched = await selectOtherPaymentMethod(page);
      test.skip(!switched, "store has no alternative payment method");
      await selectCustomCheckout(page);

      await expectSuperTokenVisible(page);
      expect(metrics.count("can_use_super_token")).toBe(0);
    });

    test("Given the saved cards are loaded, When a coupon is applied, Then the checkout shows loading and the Super Token resets", async ({ page }) => {
      await startCustomCheckout(page, buyer);
      await expectSuperTokenVisible(page);

      const reset = expectMetric(page, "super_token_reset_on_amount_change", 40000);
      await applyCoupon(page, RESET_COUPON);
      await expectCheckoutLoading(page);
      await reset;
      // Após o reset por amount change o ST recarrega sozinho (sem precisar re-disparar o email).
      // toBeVisible auto-retrying é suficiente; recovery seria contraproducente aqui.
      await expect(page.locator(SELECTORS.savedCardsList)).toBeVisible({ timeout: 30000 });
    });

    test("Given the MP session expires after the preview, When the buyer reloads the methods, Then it falls back to the standard checkout", async ({ page, faults }) => {
      await startCustomCheckout(page, buyer);
      await expectSuperTokenVisible(page);

      await faults.respondUrl(ACCOUNT_DATA, { status: 401, body: { error: "unauthorized" } });
      // Recarrega o checkout do zero (reload = "reloads the methods"): no bundle homol o soft-toggle
      // (trocar método e voltar) não re-busca account-payment-methods — fica em cache JS. Só uma nova
      // navegação re-inicializa o SDK e re-dispara o fetch, que agora pega o 401 → sem ST.
      await reopenCheckout(page, buyer);

      await expectCustomCheckoutWithoutSuperToken(page);
    });

    // (Removido) "front-end error + troca de email reseta e limpa o erro": inviável como cenário
    // isolado — o notice de erro do ST só aparece em erro de authorize/identidade (cancelamento da
    // biometria), e selecionar um cartão NÃO dispara request (comprovado via CDP), então não há
    // caminho não-biométrico para o erro. A exibição do notice já é coberta pelo B10 (authorization).

    test("Given the Order API returns an error, When the buyer places the order, Then it is not completed", async ({ page, faults }) => {
      await startCheckoutReadyToPay(page, buyer);
      await expectSuperTokenVisible(page);

      await faults.respondUrl(ORDER_API, { status: 500, body: { message: "order failed" } });
      await selectFirstSavedCard(page);
      await fillSecurityCode(page, "123");
      await placeOrder(page);

      await expect(page).not.toHaveURL(/order-received/);
    });
  });
}
