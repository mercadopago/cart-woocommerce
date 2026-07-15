import { expect, test } from "@playwright/test";
import { fillStepsToCheckout } from "./fill_steps_to_checkout";
import { placeOrder } from "./place_order.helper";

const MP_CHECKOUT_URL = /mercadopago\.[a-z.]+\/(checkout|credits)/;

// Like Checkout Pro, the redirect to the MP credits page loads an external resource whose timing is unstable on the sandbox (and slower in Blocks). We wait generously and extend the per-test timeout so the wait never outlives the test budget.
const REDIRECT_TIMEOUT = 60000;
const REDIRECT_TEST_TIMEOUT = 120000;

export async function successfulPaymentTest(page, url, user) {
  test.setTimeout(REDIRECT_TEST_TIMEOUT);
  await fillStepsToCheckout(page, url, user);
  await page.waitForLoadState();

  // Select credits gateway — supports both Classic and Blocks
  const classicRadio = page.locator('#payment_method_woo-mercado-pago-credits');
  const blocksRadio = page.locator('#radio-control-wc-payment-method-options-woo-mercado-pago-credits');

  if (await classicRadio.isVisible({ timeout: 3000 }).catch(() => false)) {
    await page.locator('label[for="payment_method_woo-mercado-pago-credits"]').click();
  } else {
    await blocksRadio.check();
  }

  await page.waitForLoadState();
  await page.waitForTimeout(3000);

  // Click place order (Classic single-click / Blocks two-phase submit)
  await placeOrder(page);

  // Let the order submission settle before waiting for the redirect (Blocks is slower).
  await page.waitForLoadState();

  // Plugin scope: verify redirect to MP checkout/credits page
  await page.waitForURL(MP_CHECKOUT_URL, { timeout: REDIRECT_TIMEOUT });
  await expect(page).toHaveURL(MP_CHECKOUT_URL);
}
