import { test } from "@playwright/test";
import { mla } from "../../../data/meli_sites";
import { orderIdFromReceivedUrl } from "../../../flows/refund";
import { goToCustomCheckout, fillCustomCardForm, placeOrder } from "../../../flows/chocustom";
import {
  snapshotGatewaySettings,
  restoreGatewaySettings,
  setCurrencyConversion,
  assertCurrencyConverted,
  assertCurrencyRatioMatchesLiveRate,
  captureCurrencyScreenshot,
  writeCurrencyProof,
} from "../../../flows/amount_config";

const { wpOption, wpGetOption } = require("../../../helpers/wp-env");
const { skipIfNotSite } = require("../../../helpers/site-guard");

const { shop_url, credit_card_scenarios, guestUserMLA } = mla;
const { APPROVED } = credit_card_scenarios;

const CUSTOM_GATEWAY = "woo-mercado-pago-custom";
// The reported bug reproduction was exactly this combination: MLA credentials with the
// WooCommerce store currency set to BRL. The old /currency_conversions/search endpoint was
// blocked by PolicyAgent (PA_UNAUTHORIZED_RESULT_FROM_POLICIES) for MLA sellers converting from
// BRL; migrating to the ppcore exchange endpoint is what this scenario proves fixed. MLA settles
// in ARS, so BRL still differs from the account currency — the precondition for conversion.
const STORE_CURRENCY = "BRL";

skipIfNotSite(test, "MLA");

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
});

test.describe("currency conversion @serial-store", () => {
  test("Given MLA credentials with the store currency set to BRL (the reported bug's exact repro) and conversion is on, When the buyer pays with an approved card, Should charge using the live ppcore exchange ratio instead of failing with PolicyAgent's PA_UNAUTHORIZED_RESULT_FROM_POLICIES", async ({ page }) => {
    wpOption("woocommerce_currency", STORE_CURRENCY);
    setCurrencyConversion(CUSTOM_GATEWAY, true);

    await goToCustomCheckout(page, shop_url, guestUserMLA);
    await captureCurrencyScreenshot(page, "mla-01-checkout-before-payment-brl.png");

    await fillCustomCardForm(page, APPROVED.master, APPROVED.form);
    await placeOrder(page);
    await page.waitForURL(/order-received/, { waitUntil: "domcontentloaded", timeout: 60000 });
    await captureCurrencyScreenshot(page, "mla-02-order-received.png");

    const orderId = orderIdFromReceivedUrl(page);
    assertCurrencyConverted(orderId);

    const { storedRatio, liveRate } = await assertCurrencyRatioMatchesLiveRate(
      orderId,
      process.env.MP_ACCESS_TOKEN_TEST_MLA,
      STORE_CURRENCY,
      "beta"
    );
    writeCurrencyProof("mla-03-ratio-proof.json", {
      site: "MLA",
      order_id: orderId,
      store_currency: STORE_CURRENCY,
      account_currency: "ARS",
      currency_ratio_stored_on_order: storedRatio,
      live_ppcore_exchange_rate: liveRate,
    });
  });
});
