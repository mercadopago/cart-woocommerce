import { test, expect } from "@playwright/test";
import { mlb } from "../../../data/meli_sites";
import {
  orderIdFromReceivedUrl,
  attachRefundDialogHandler,
  openRefundPanel,
  setLineRefundAmount,
  setFullRefundAmounts,
  clickApiRefund,
  getItemLineTotal,
  getOrderStatus,
  markOrderAsSuperToken,
  assertRefundSuccess,
  assertTotalRefunded,
  assertRefundError,
  syncPaymentStatus,
} from "../../../flows/refund";
import { successfulPaymentTest } from "../../../flows/chocustom";

const { wpOption, wpGetOption, wpEval, setGatewaySetting } = require("../../../helpers/wp-env");
const { skipIfNotSite } = require("../../../helpers/site-guard");

const { shop_url, credit_card_scenarios, guestUserMLB } = mlb;
const { APPROVED, PENDING } = credit_card_scenarios;

const CUSTOM_GATEWAY = "woo-mercado-pago-custom";

// global-setup writes the active credential to the *_prod slot when MP_ENV=prod and
// to the *_test slot otherwise; the auth-error scenario must corrupt the slot in use.
const MP_ENV = (process.env.MP_ENV || "test").toLowerCase();
const ACCESS_TOKEN_OPTION = MP_ENV === "prod" ? "_mp_access_token_prod" : "_mp_access_token_test";

skipIfNotSite(test, "MLB");

// Serial so the invalid-credential test's broken-token window never overlaps another payment.
test.describe.configure({ mode: "serial" });

function roundMoney(value) {
  return Math.round(value * 100) / 100;
}

async function payWithApprovedCardAndGetOrderId(page) {
  await successfulPaymentTest(page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form);
  const orderId = orderIdFromReceivedUrl(page);
  // The IPN never reaches localhost, so the order stays "pending" and the Refund button won't render. Trigger the plugin's sync metabox to move it to "processing".
  await syncPaymentStatus(page, shop_url, orderId);
  return orderId;
}

test.describe("successful refunds via WC admin", () => {
  test("Given an approved card payment, When the admin refunds half the amount, Should record the refund and keep the order in history", async ({ page }) => {
    const dialog = attachRefundDialogHandler(page);
    const orderId = await payWithApprovedCardAndGetOrderId(page);
    const half = roundMoney(getItemLineTotal(orderId) / 2);

    expect(await openRefundPanel(page, shop_url, orderId)).toBe(true);
    await setLineRefundAmount(page, half);
    await clickApiRefund(page, dialog);

    await assertRefundSuccess(page, orderId, half);
    expect(getOrderStatus(orderId)).not.toBe("refunded");
  });

  test("Given an approved card payment, When the admin refunds the full amount, Should change the order status to refunded", async ({ page }) => {
    const dialog = attachRefundDialogHandler(page);
    const orderId = await payWithApprovedCardAndGetOrderId(page);

    expect(await openRefundPanel(page, shop_url, orderId)).toBe(true);
    await setFullRefundAmounts(page, orderId);
    await clickApiRefund(page, dialog);

    await assertTotalRefunded(page, orderId);
  });

  test("Given an order with a partial refund already applied, When the admin refunds the remaining amount, Should change the order status to refunded", async ({ page }) => {
    const dialog = attachRefundDialogHandler(page);
    const orderId = await payWithApprovedCardAndGetOrderId(page);
    const part = roundMoney(getItemLineTotal(orderId) / 4);

    expect(await openRefundPanel(page, shop_url, orderId)).toBe(true);
    await setLineRefundAmount(page, part);
    await clickApiRefund(page, dialog);
    await assertRefundSuccess(page, orderId, part);

    expect(await openRefundPanel(page, shop_url, orderId)).toBe(true);
    await setFullRefundAmounts(page, orderId);
    await clickApiRefund(page, dialog);
    await assertTotalRefunded(page, orderId);
  });
});

test.describe("refund errors", () => {
  test("Given an approved card payment, When the admin tries to refund zero, Should show an error and leave the order unchanged", async ({ page }) => {
    const dialog = attachRefundDialogHandler(page);
    const orderId = await payWithApprovedCardAndGetOrderId(page);

    expect(await openRefundPanel(page, shop_url, orderId)).toBe(true);
    await setLineRefundAmount(page, 0);
    await clickApiRefund(page, dialog);

    assertRefundError(dialog, /maior que zero/i, orderId);
  });

  // binary_mode off keeps the CONT card in_process (non-approved) while still reaching order-received.
  test.describe("with binary mode off", () => {
    let originalBinaryMode = null;

    test.beforeAll(() => {
      const settings = JSON.parse(
        wpEval(`echo wp_json_encode(get_option("woocommerce_${CUSTOM_GATEWAY}_settings", []));`) || "{}"
      );
      originalBinaryMode = settings.binary_mode ?? "no";
      setGatewaySetting(CUSTOM_GATEWAY, "binary_mode", "no");
    });

    test.afterAll(() => {
      if (originalBinaryMode !== null) {
        setGatewaySetting(CUSTOM_GATEWAY, "binary_mode", originalBinaryMode);
      }
    });

    test("Given a non-approved (initially pending) card payment, When the admin tries to refund, Should be rejected by the MP API", async ({ page }) => {
      const dialog = attachRefundDialogHandler(page);
      await successfulPaymentTest(page, shop_url, guestUserMLB, PENDING.master, PENDING.form);
      const orderId = orderIdFromReceivedUrl(page);
      // Sync moves the order off "pending" (MP in_process → on-hold) so the Refund panel renders, while the payment itself stays non-approved for the assertion.
      await syncPaymentStatus(page, shop_url, orderId);

      expect(await openRefundPanel(page, shop_url, orderId)).toBe(true);
      await setLineRefundAmount(page, roundMoney(getItemLineTotal(orderId) / 2));
      await clickApiRefund(page, dialog);

      assertRefundError(dialog, /aprovado|ocorreu um erro|não foi poss/i, orderId);
    });
  });

  // @serial-store: corrupts the shared access-token option → must not run in parallel.
  // Isolation comes from run-all-report.sh running @serial-store specs in a separate
  // phase (workers=1) after all other MLB tests finish (workers=2). See run-all-report.sh.
  test.describe("with an invalid access token @serial-store", () => {
    let savedToken = null;

    test.afterEach(() => {
      if (savedToken !== null) {
        wpOption(ACCESS_TOKEN_OPTION, savedToken);
        savedToken = null;
      }
    });

    test("Given an approved payment with stale credentials, When the admin tries to refund, Should show an authentication error", async ({ page }) => {
      const dialog = attachRefundDialogHandler(page);
      const orderId = await payWithApprovedCardAndGetOrderId(page);
      const half = roundMoney(getItemLineTotal(orderId) / 2);

      savedToken = wpGetOption(ACCESS_TOKEN_OPTION);
      if (!savedToken) {
        throw new Error(`[E2E] ${ACCESS_TOKEN_OPTION} is empty; configure test credentials before running the auth-error scenario.`);
      }

      // try/finally restores the shared credential immediately, even if an assertion throws —
      // this option is read by every request to the store, so a lingering bad token would
      // break other specs running in parallel workers (afterEach is only a safety net).
      try {
        // Well-formed but invalid token → MP 401 (credentials path); a malformed string gives a generic 403.
        wpOption(ACCESS_TOKEN_OPTION, `${savedToken.slice(0, -6)}000000`);

        expect(await openRefundPanel(page, shop_url, orderId)).toBe(true);
        await setLineRefundAmount(page, half);
        await clickApiRefund(page, dialog);

        assertRefundError(dialog, /credenciais de acesso estão incorretas ou expiradas/i, orderId);
      } finally {
        wpOption(ACCESS_TOKEN_OPTION, savedToken);
        savedToken = null;
      }
    });
  });

  test("Given a Super Token payment, When the admin tries to refund, Should show a not-supported message", async ({ page }) => {
    const dialog = attachRefundDialogHandler(page);
    const orderId = await payWithApprovedCardAndGetOrderId(page);
    markOrderAsSuperToken(orderId);
    const half = roundMoney(getItemLineTotal(orderId) / 2);

    expect(await openRefundPanel(page, shop_url, orderId)).toBe(true);
    await setLineRefundAmount(page, half);
    await clickApiRefund(page, dialog);

    assertRefundError(dialog, /Pagamentos rápidos|não suporta reembolso/i, orderId);
  });
});
