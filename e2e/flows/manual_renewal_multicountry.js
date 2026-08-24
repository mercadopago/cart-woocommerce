import { test } from "@playwright/test";
import { setupSubscriptionsEnvironment, cleanupSubscriptionProduct } from "../helpers/subscriptions-env";
import { wpEval, wpOption } from "../helpers/wp-env";
import { skipIfNotSite, getStoreSiteId } from "../helpers/site-guard";
import { snapshotOptions, restoreOptions } from "../helpers/wp-options-snapshot";
import {
  trackCorsErrors,
  addSubscriptionToCartAndCheckout,
  fillClassicBillingForm,
  selectMpCustomPaymentMethod,
  applySubscriptionCardEntryPatches,
  fillMpSecureFields,
  placeOrder,
  assertOrderReceived,
} from "./subscriptions";

/**
 * Registers the "Accept Manual Renewals" single-payment scenario for one country, so
 * the same check runs across the subscription-supported markets without duplicating
 * the setup. Each per-country spec calls this once with its own data/user.
 *
 * The guard exercised (gateway with a subscription in cart but no Pre-approval
 * credentials → processed as a single payment) lives in process_payment(), which is
 * country-agnostic; this widens the coverage to each market's checkout/card/doc.
 *
 * @serial-store: the setup mutates store-wide options (subscription toggle,
 * Pre-approval tokens, WCS accept_manual_renewals / turn_off_automatic_payments) and
 * installs/activates WCS, so it must not run in parallel with other specs. The tag is
 * carried on the describe so run-all-report.sh runs it alone (workers=1) in the
 * serial phase.
 *
 * The gateway settings are snapshotted (base64 JSON) in beforeAll and restored in
 * afterAll so recurrence specs are unaffected regardless of run order.
 */

/**
 * Installments + document + cardholder fill that gates the identification select on
 * visibility, so markets without a document field (e.g. MLM) are handled gracefully
 * — unlike the shared fillInstallmentsForm, which assumes the doc select exists.
 */
async function fillInstallmentsRobust(page, form) {
  const installments = page.locator("#mp-checkout-custom-installments-card");
  if (!(await installments.isVisible().catch(() => false))) return;

  const idType = page.locator("#form-checkout__identificationType");
  if (await idType.isVisible().catch(() => false)) {
    const hasDoc = await idType
      .locator(`option[value="${form.docType}"]`)
      .count()
      .then((n) => n > 0)
      .catch(() => false);
    if (hasDoc) await idType.selectOption(form.docType);
    await page.waitForTimeout(200);
    if (form.docNumber != null) {
      await page.locator('[name="identificationNumber"]').fill(form.docNumber);
    }
  }

  await page.locator("#form-checkout__cardholderName").fill(form.name);
  if (form.name !== "") {
    await page.locator("#form-checkout__installments").selectOption({ index: 1 });
  }
  await page.waitForLoadState();
}

/**
 * @param {string} siteId     e.g. 'MLA'
 * @param {object} siteData   the meli_sites entry (shop_url, credit_card_scenarios)
 * @param {object} guestUser  the country's guest buyer
 */
export function registerManualRenewalSinglePaymentScenario(siteId, siteData, guestUser) {
  const origin = new URL(siteData.shop_url).origin;
  const productUrl = origin + (process.env.SUBSCRIPTION_PRODUCT_URL || "/product/subscription-test-product/");
  const { APPROVED } = siteData.credit_card_scenarios;

  test.describe(`Accept Manual Renewals single payment ${siteId} @serial-store`, () => {
    // All store state this spec mutates: the gateway settings + the two WCS global
    // options. Snapshotted BEFORE setup and restored to the genuine pre-test state
    // (absent options are restored by deletion, not recreated as "").
    const STORE_OPTIONS = [
      "woocommerce_woo-mercado-pago-custom_settings",
      "woocommerce_subscriptions_accept_manual_renewals",
      "woocommerce_subscriptions_turn_off_automatic_payments",
    ];
    let storeSnapshot = null;

    test.beforeEach(() => skipIfNotSite(test, siteId));

    test.beforeAll(() => {
      // beforeAll runs even when the per-test site guard will skip, so a single-site
      // run still loads every country's factory. Do nothing unless the store IS this
      // country — never mutate the shared store on another country's behalf.
      if (getStoreSiteId() !== siteId) return;

      // Snapshot BEFORE setup (which seeds subscriptions_enabled + the Pre-approval
      // tokens) so afterAll restores the store's real pre-test configuration.
      storeSnapshot = snapshotOptions(STORE_OPTIONS);

      setupSubscriptionsEnvironment();

      wpEval(
        `$s = get_option("woocommerce_woo-mercado-pago-custom_settings", []);` +
        `$s["subscriptions_enabled"] = "no";` +
        `unset($s["subscriptions_access_token_test"]);` +
        `unset($s["subscriptions_access_token_prod"]);` +
        `update_option("woocommerce_woo-mercado-pago-custom_settings", $s);`
      );

      wpOption("woocommerce_subscriptions_accept_manual_renewals", "yes");
      wpOption("woocommerce_subscriptions_turn_off_automatic_payments", "yes");
    });

    test.afterAll(() => {
      restoreOptions(storeSnapshot);
      // Remove o produto de subscription para não contaminar os testes de card/pix/ticket.
      cleanupSubscriptionProduct();
    });

    test(`Given a subscription without Pre-approval credentials and manual renewals enabled in ${siteId}, When it is paid on the Classic checkout, Then it is processed as a single payment and the order-received page is shown`, async ({ page }) => {
      test.skip(process.env.CHECKOUT === "blocks", "Classic-only multi-country subscription scenario");
      test.setTimeout(90000);

      const uniqueEmail = `test_user_${Date.now()}@testuser.com`;
      const corsErrors = trackCorsErrors(page);

      await addSubscriptionToCartAndCheckout(page, productUrl);
      await fillClassicBillingForm(page, guestUser, uniqueEmail);
      await selectMpCustomPaymentMethod(page);
      await applySubscriptionCardEntryPatches(page);
      await fillMpSecureFields(page, APPROVED.master);
      await fillInstallmentsRobust(page, APPROVED.form);
      await placeOrder(page);

      await assertOrderReceived(page, corsErrors);
    });
  });
}
