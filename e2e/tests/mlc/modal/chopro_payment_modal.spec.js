import { test, expect } from '@playwright/test';
import { mlc } from '../../../data/meli_sites';
import { fillStepsToCheckout } from '../../../flows/fill_steps_to_checkout';
import { payChoproModalLogged, payChoproModalGuest, returnToCongratsPageModal } from '../../../flows/mlc/pay_with_cho_pro';

const { shop_url, credit_card_scenarios, loggedUserMLC } = mlc;
const { APPROVED, REJECTED, PENDING } = credit_card_scenarios;

test('test successful payment logged in with chopro, payment must be approved and success page must be shown', async ({ page }) => {
  await fillStepsToCheckout(page, shop_url, loggedUserMLC);
  await payChoproModalLogged(page, APPROVED.master, APPROVED.form, loggedUserMLC, 'Tarjeta de crédito');

  await returnToCongratsPageModal(page);

  await page.waitForLoadState();
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
});

test('test rejected payment logged in with chopro, payment must be rejected and decline message must be shown', async ({ page }) => {
  const modal = page.locator('#mercadopago-checkout').contentFrame();

  await fillStepsToCheckout(page, shop_url, loggedUserMLC);
  await payChoproModalLogged(page, REJECTED.master, REJECTED.form, loggedUserMLC, 'Tarjeta de crédito');

  const changePaymentMethod = modal.locator('#change_payment_method');
  await expect(changePaymentMethod).toBeVisible();
  await changePaymentMethod.click();

  await page.waitForLoadState();
  await expect(modal.locator('#root-app')).toHaveText(/¿Cómo quieres pagar?/i);
});

test('test pending payment logged in with chopro, payment must be pending and the payment processing message must be shown', async ({ page }) => {
  const modal = page.locator('#mercadopago-checkout').contentFrame();

  await fillStepsToCheckout(page, shop_url, loggedUserMLC);
  await payChoproModalLogged(page, PENDING.master, PENDING.form, loggedUserMLC, 'Tarjeta de crédito');
  await page.waitForTimeout(2000);
  
  await page.waitForLoadState();
  await expect(modal.locator('#root-app')).toHaveText(/¿Qué puedo hacer?/i);
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
});

test('test successful payment guest with chopro, payment must be approved and success page must be shown', async ({ page }) => {
  await fillStepsToCheckout(page, shop_url, loggedUserMLC);
  await payChoproModalGuest(page, APPROVED.master, APPROVED.form, 'Tarjeta de crédito');
  
  await returnToCongratsPageModal(page);

  await page.waitForLoadState();
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
});

test('test rejected payment guest with chopro, payment must be rejected and decline message must be shown', async ({ page }) => {
  const modal = page.locator('#mercadopago-checkout').contentFrame();

  await fillStepsToCheckout(page, shop_url, loggedUserMLC);
  await payChoproModalGuest(page, REJECTED.master, REJECTED.form, 'Tarjeta de crédito');
  
  const changePaymentMethod = modal.locator('#change_payment_method');
  await expect(changePaymentMethod).toBeVisible();
  await changePaymentMethod.click();

  await page.waitForLoadState();
  await expect(modal.locator('#root-app')).toHaveText(/¿Cómo quieres pagar?/i);
});

test('test pending payment guest with chopro, payment must be pending and the payment processing message must be shown', async ({ page }) => {
  const modal = page.locator('#mercadopago-checkout').contentFrame();
  
  await fillStepsToCheckout(page, shop_url, loggedUserMLC);
  await payChoproModalGuest(page, PENDING.master, PENDING.form, 'Tarjeta de crédito');

  await page.waitForLoadState();
  await expect(modal.locator('#root-app')).toHaveText(/¿Qué puedo hacer?/i);
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
});
