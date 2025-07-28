import { expect } from "@playwright/test";
import { fillStepsToCheckout } from "./fill_steps_to_checkout";

export async function getModal(page) {
  return await page.locator('#mercadopago-checkout').contentFrame();
}

export async function modalSuccessfulPaymentTest({ page, url, user, card, form }) {
  await successfulPaymentTest({
    page,
    url,
    user,
    card,
    form,
    getOrigin: getModal,
    getReturnButton: async page => (await getModal(page)).locator('#group_button_back_congrats')
  });
}

export async function modalSuccessfulPendingPaymentTest({ page, url, user, card, form }) {
  await successfulPaymentTest({
    page,
    url,
    user,
    card,
    form,
    getOrigin: getModal,
    getReturnButton: async page => (await getModal(page)).locator('#button'),
    checkThankyou: false
  });
}

export async function redirectSuccessfulPaymentTest({ page, url, user, card, form }) {
  await successfulPaymentTest({
    page,
    url,
    user,
    card,
    form,
    getOrigin: page => page,
    getReturnButton: page => page.locator('#group_button_back_congrats')
  });
}

export async function redirectSuccessfulPendingPaymentTest({ page, url, user, card, form }) {
  await successfulPaymentTest({
    page,
    url,
    user,
    card,
    form,
    getOrigin: page => page,
    getReturnButton: page => page.locator('#button')
  });
}

async function successfulPaymentTest({ page, url, user, card, form, getOrigin, getReturnButton, checkThankyou = true }) {
  await fillStepsToCheckout(page, url, user);
  await makePayment(page, card, form, getOrigin);

  await page.waitForTimeout(5000);

  const returnButton = await getReturnButton(page);
  await expect(returnButton).toBeVisible();
  returnButton.click();

  await page.waitForLoadState();

  if (checkThankyou) {
    await expect(page.locator('.woocommerce-thankyou-order-received')).toBeVisible();
  }
}

export async function modalRejectAndChangeMethodTest({ page, url, user, card, form }) {
  await rejectAndChangeMethodTest({
    page,
    url,
    user,
    card,
    form,
    getOrigin: getModal
  });
}

export async function redirectRejectAndChangeMethodTest({ page, url, user, card, form }) {
  await rejectAndChangeMethodTest({
    page,
    url,
    user,
    card,
    form,
    getOrigin: page => page
  });
}

async function rejectAndChangeMethodTest({ page, url, user, card, form, getOrigin }) {
  await fillStepsToCheckout(page, url, user);
  await makePayment(page, card, form, getOrigin);

  const origin = await getOrigin(page);
  const changePaymentMethod = origin.locator('#change_payment_method');
  await expect(changePaymentMethod).toBeVisible();
  await changePaymentMethod.click();

  await page.waitForLoadState();
  await page.waitForTimeout(3000);
  await expect(origin.locator('.payment-option-desktop-screen__content')).toBeVisible();
}

export async function redirectCancelOrderTest({ page, url, user, card, form }) {
  await fillStepsToCheckout(page, url, user);
  await makePayment(page, card, form, page => page);

  const returnButton = page.locator('.group-back-url a');
  await expect(returnButton).toBeVisible();
  returnButton.click();

  await page.waitForTimeout(3000);
  await expect(page).toHaveURL(/.*cancel_order=true.*/);
}

export async function modalCancelOrderTest({ page, url, user, card, form }) {
  await fillStepsToCheckout(page, url, user);
  await makePayment(page, card, form, getModal);

  await page.waitForTimeout(5000);

  const modal = await getModal(page);

  await modal.locator('#mp-close-btn').click();
  await page.waitForLoadState();
  const closeAndCancel = modal.locator('.fullscreen-message__button-secondary');
  await expect(closeAndCancel).toBeVisible();
  await closeAndCancel.click();
  await page.waitForLoadState();
  await expect(page).toHaveURL(/.*cancel_order=true.*/);
}

async function makePayment(page, card, form, getOrigin) {
  await page.locator('#radio-control-wc-payment-method-options-woo-mercado-pago-basic').check();
  await page.locator('.wc-block-components-checkout-place-order-button').click();

  const origin = await getOrigin(page);

  await page.waitForLoadState();
  await origin.locator('#new_card_row').click();
  await page.waitForLoadState();
  await origin.frameLocator('iframe[name="cardNumber"]').locator('[name="cardNumber"]').fill(card.number);
  await origin.locator('#cardholderName').fill(form.name);
  await page.waitForLoadState();
  await origin.frameLocator('iframe[name="expirationDate"]').locator('[name="expirationDate"]').fill(card.date);
  await page.waitForLoadState();
  await origin.frameLocator('iframe[name="securityCode"]').locator('[name="securityCode"]').fill(card.code);
  await page.waitForTimeout(2000);

  if (form?.docType) {
    await origin.locator('#cardholderIdentificationNumber-dropdown-trigger').click();
    await origin.getByRole('option', { name: form.docType }).click();
    await page.waitForLoadState();
    await origin.getByTestId('identification-types--field').fill(form.docNumber);
    await page.waitForTimeout(2000);
  }

  await origin.locator('.continue_button').click();

  await page.waitForTimeout(3000);

  const installments = await origin.locator('ul li:first-child');

  if (await installments.isVisible()) {
    await installments.click();
  }

  await page.waitForLoadState();
  await origin.locator('.cow-payment_summary__button').click();

  await page.waitForLoadState();
}
