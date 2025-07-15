import payWithCard from "./pay_with_card";
import { fillStepsToCheckout } from "./fill_steps_to_checkout";
import { expect } from "@playwright/test";

export async function successfulPaymentTest(page, url, user, card, form) {
  await makePayment(page, url, user, card, form);
  await page.waitForTimeout(2000);
  await expect(page.locator('.woocommerce-thankyou-order-received')).toBeVisible();
}

export async function rejectedPaymentTest(page, url, user, card, form) {
  await makePayment(page, url, user, card, form);
  await expect(page.locator('.wc-block-store-notice.wc-block-components-notice-banner.is-error')).toBeVisible();
}

export async function emptyFieldsPaymentTest(page, url, user, card, form) {
  await makePayment(page, url, user, card, form);

  for (const helper of [
    '#mp-card-holder-div input-helper',
    '#mp-installments-helper',
    '#mp-card-holder-div input-helper'
  ]) {
    expect(
      await page
        .locator(helper)
        .evaluate(element => window.getComputedStyle(element).display)
    ).not.toBe('none');
  }
}

async function makePayment(page, url, user, card, form) {
  await fillStepsToCheckout(page, url, user);
  await payWithCard(page, card, form);
}
