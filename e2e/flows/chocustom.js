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
  await page.waitForLoadState();
  await page.locator("#radio-control-wc-payment-method-options-woo-mercado-pago-custom").check();

  await page.waitForLoadState();

  await page.frameLocator('iframe[name="cardNumber"]').locator('[name="cardNumber"]').fill(card.number);
  await page.frameLocator('iframe[name="securityCode"]').locator('[name="securityCode"]').fill(card.code);
  await page.frameLocator('iframe[name="expirationDate"]').locator('[name="expirationDate"]').fill(card.date);
  await page.waitForLoadState();

  await page.waitForLoadState();

  const installments = await page.locator('#mp-checkout-custom-installments')

  await page.waitForTimeout(2000);

  if (await installments.isVisible()) {
    await page.locator('#form-checkout__identificationType').selectOption(form.docType);
    await page.waitForTimeout(200);
    await page.locator('[name="identificationNumber"]').fill(form.docNumber);
    await page.locator('#form-checkout__cardholderName').fill(form.name);

    if (form.name !== '') {
      await page.locator('.mp-input-radio-container').first().click();
    }
    await page.waitForLoadState();
  }

  await page.locator('.wc-block-components-checkout-place-order-button').click();

  await page.waitForLoadState();
}
