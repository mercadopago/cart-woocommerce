import { expect } from "@playwright/test";
import { fillStepsToCheckout } from "./fill_steps_to_checkout";

const MP_CHECKOUT_URL = /mercadopago\.[a-z.]+\/(checkout|credits)/;

export async function successfulPaymentTest(page, url, user) {
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

  await page.waitForTimeout(1000);

  // Click place order — supports both Classic and Blocks
  const classicPlaceOrder = page.locator('#place_order');
  const blocksPlaceOrder = page.locator('.wc-block-components-checkout-place-order-button');

  if (await classicPlaceOrder.isVisible({ timeout: 3000 }).catch(() => false)) {
    await classicPlaceOrder.click();
  } else {
    await blocksPlaceOrder.click();
  }

  // Plugin scope: verify redirect to MP checkout/credits page
  await page.waitForURL(MP_CHECKOUT_URL, { timeout: 30000 });
  await expect(page).toHaveURL(MP_CHECKOUT_URL);
}
