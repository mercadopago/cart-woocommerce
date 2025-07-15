import { test, expect } from '@playwright/test';
import { mlu } from '../../../../data/meli_sites';
import { fillStepsToCheckout } from '../../../../flows/fill_steps_to_checkout';
import { payChoproModalLoggedTarjeta, payChoproModalGuestTarjeta, returnToCongratsPageModal } from '../../../../flows/mlu/pay_with_cho_pro';

const { shop_url, credit_card_scenarios, loggedUserMLU, guestUser } = mlu;
const { APPROVED, REJECTED, PENDING } = credit_card_scenarios;

test('test successful payment logged in with chopro, payment must be approved and success page must be shown', async ({ page }) => {
  await fillStepsToCheckout(page, shop_url, loggedUserMLU);
  await payChoproModalLoggedTarjeta(page, APPROVED.master, APPROVED.form, loggedUserMLU, 'Tarjeta de crédito');
  await returnToCongratsPageModal(page);

  await page.waitForLoadState();
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
});

test('test rejected payment logged in with chopro, payment must be rejected and decline message must be shown', async ({ page }) => {
  const modal = page.locator('#mercadopago-checkout').contentFrame();
  await fillStepsToCheckout(page, shop_url, loggedUserMLU);
  await payChoproModalLoggedTarjeta(page, REJECTED.master, REJECTED.form, loggedUserMLU, 'Tarjeta de crédito');

  const changePaymentMethod = modal.locator('#change_payment_method');
  await expect(changePaymentMethod).toBeVisible();
  await changePaymentMethod.click();

  await page.waitForLoadState();
  await expect(modal.locator('#root-app')).toHaveText(/¿Cómo quieres pagar?/i);
});

test('test pending payment logged in with chopro, payment must be pending and the payment processing message must be shown', async ({ page }) => {
  const modal = page.locator('#mercadopago-checkout').contentFrame();

  await fillStepsToCheckout(page, shop_url, loggedUserMLU);
  await payChoproModalLoggedTarjeta(page, PENDING.master, PENDING.form, loggedUserMLU, 'Tarjeta de crédito');
  await page.waitForTimeout(2000);
  
  await page.waitForLoadState();
  await expect(modal.locator('#root-app')).toHaveText(/¿Qué puedo hacer?/i);
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
});

test('test successful payment guest with chopro, payment must be approved and success page must be shown', async ({ page }) => {
  await fillStepsToCheckout(page, shop_url, guestUser);
  await payChoproModalGuestTarjeta(page, APPROVED.master, APPROVED.form, 'Tarjeta de crédito');
  await returnToCongratsPageModal(page);

  await page.waitForLoadState();
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
});

test('test rejected payment guest with chopro, payment must be rejected and decline message must be shown', async ({ page }) => {
  const modal = page.locator('#mercadopago-checkout').contentFrame();
  await fillStepsToCheckout(page, shop_url, guestUser);
  await payChoproModalGuestTarjeta(page, REJECTED.master, REJECTED.form, 'Tarjeta de crédito');
  
  const changePaymentMethod = modal.locator('#change_payment_method');
  await expect(changePaymentMethod).toBeVisible();
  await changePaymentMethod.click();

  await page.waitForLoadState();
  await expect(modal.locator('#root-app')).toHaveText(/¿Cómo quieres pagar?/i);
});

test('test pending payment guest with chopro, payment must be pending and the payment processing message must be shown', async ({ page }) => {
  const modal = page.locator('#mercadopago-checkout').contentFrame();
  await fillStepsToCheckout(page, shop_url, guestUser);
  await payChoproModalGuestTarjeta(page, PENDING.master, PENDING.form, 'Tarjeta de crédito');

  await page.waitForLoadState();
  await expect(modal.locator('#root-app')).toHaveText(/¿Qué puedo hacer?/i);
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
});
