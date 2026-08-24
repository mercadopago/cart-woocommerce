import { test } from "@playwright/test";
import { mlc } from "../../../data/meli_sites";
import { orderIdFromReceivedUrl } from "../../../flows/refund";
import { goToCustomCheckout, fillCustomCardForm, placeOrder } from "../../../flows/chocustom";
import {
  snapshotGatewaySettings,
  restoreGatewaySettings,
  setCurrencyConversion,
  setAllProductsPrice,
  assertCurrencyConverted,
  assertCurrencyRatioMatchesLiveRate,
  captureCurrencyScreenshot,
  writeCurrencyProof,
} from "../../../flows/amount_config";

const { wpOption, wpGetOption } = require("../../../helpers/wp-env");
const { skipIfNotSite } = require("../../../helpers/site-guard");

const { shop_url, credit_card_scenarios, guestUserMLC } = mlc;
const { APPROVED } = credit_card_scenarios;

const CUSTOM_GATEWAY = "woo-mercado-pago-custom";
// MLC settles in CLP; forcing the store to USD makes the store currency differ from the
// account currency, which is the precondition for currency_ratio conversion.
const STORE_CURRENCY = "USD";
// global-setup.js prices MLC products at 100000 (tuned for native CLP nominal scale). Once the
// store currency is USD, that same 100000 gets re-scaled by the live ~928 CLP/USD ratio into an
// unrealistic ~93M CLP charge, which MP's API rejects ("Invalid installments"). Reprice to a
// realistic USD amount for the duration of this scenario — see setAllProductsPrice() docblock.
const REALISTIC_STORE_CURRENCY_PRICE = "400.37";
const NATIVE_ACCOUNT_CURRENCY_PRICE = "100000";

skipIfNotSite(test, "MLC");

let gatewaySnapshot = null;
let originalCurrency = null;

test.beforeAll(() => {
  gatewaySnapshot = snapshotGatewaySettings(CUSTOM_GATEWAY);
  originalCurrency = wpGetOption("woocommerce_currency");
});

test.afterEach(() => {
  restoreGatewaySettings(CUSTOM_GATEWAY, gatewaySnapshot);
  if (originalCurrency) {
    wpOption("woocommerce_currency", originalCurrency);
  }
  setAllProductsPrice(NATIVE_ACCOUNT_CURRENCY_PRICE);
});

test.describe("currency conversion @serial-store", () => {
  test("Given the store currency (USD) differs from the MLC account currency (CLP) and conversion is on, When the buyer pays with an approved card, Should charge using the live ppcore exchange ratio", async ({ page }) => {
    wpOption("woocommerce_currency", STORE_CURRENCY);
    setCurrencyConversion(CUSTOM_GATEWAY, true);
    setAllProductsPrice(REALISTIC_STORE_CURRENCY_PRICE);

    await goToCustomCheckout(page, shop_url, guestUserMLC);
    await captureCurrencyScreenshot(page, "mlc-01-checkout-before-payment-usd.png");

    await fillCustomCardForm(page, APPROVED.master, APPROVED.form);
    await placeOrder(page);
    await page.waitForURL(/order-received/, { waitUntil: "domcontentloaded", timeout: 60000 });
    await captureCurrencyScreenshot(page, "mlc-02-order-received.png");

    const orderId = orderIdFromReceivedUrl(page);
    assertCurrencyConverted(orderId);

    const { storedRatio, liveRate } = await assertCurrencyRatioMatchesLiveRate(
      orderId,
      process.env.MP_ACCESS_TOKEN_TEST_MLC,
      STORE_CURRENCY,
      "beta"
    );
    writeCurrencyProof("mlc-03-ratio-proof.json", {
      site: "MLC",
      order_id: orderId,
      store_currency: STORE_CURRENCY,
      account_currency: "CLP",
      currency_ratio_stored_on_order: storedRatio,
      live_ppcore_exchange_rate: liveRate,
    });
  });
});
