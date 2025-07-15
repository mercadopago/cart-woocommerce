import { fillStepsToCheckout } from "./fill_steps_to_checkout";
import { expect } from "@playwright/test";
import payWithInvoice from './pay_with_invoice';

export async function successfulPaymentTest(page, url, user, method = null) {
  await fillStepsToCheckout(page, url, user);
  await payWithInvoice(page, user, method);

  await expect(page.locator('#submit-payment')).toBeVisible();
  await expect(page.locator('.woocommerce-thankyou-order-received')).toBeVisible();
}

export async function rejectedPaymentTest(page, url, user, method = null) {
  await fillStepsToCheckout(page, url, user);
  await payWithInvoice(page, user, method);

  await expect(page.locator('.wc-block-store-notice.wc-block-components-notice-banner.is-error')).toBeVisible();
}
