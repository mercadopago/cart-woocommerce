import { test, expect } from '@playwright/test';
import { mlu } from '../../../../data/meli_sites';
import { fillStepsToCheckout } from '../../../../flows/fill_steps_to_checkout';
import { payChoProRedirectLoggedTarjeta, returnToCongratsPageRedirect, payChoProRedirectGuestTarjeta } from '../../../../flows/mlu/pay_with_cho_pro';

const { url, credit_card_scenarios, loggedUserMLU, guestUser } = mlu;
const { APPROVED, REJECTED, PENDING } = credit_card_scenarios;

test('test credit card payment test with approved status and logged in user', async ({ page }) => {
  await fillStepsToCheckout(page, url, loggedUserMLU);
  await payChoProRedirectLoggedTarjeta(page, APPROVED.master, APPROVED.form, loggedUserMLU, 'Tarjeta de crédito');
  await returnToCongratsPageRedirect(page);
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
});

test('test credit card payment test with rejected status and logged in user', async ({ page }) => {
  await fillStepsToCheckout(page, url, loggedUserMLU);
  await payChoProRedirectLoggedTarjeta(page, REJECTED.master, REJECTED.form, loggedUserMLU, 'Tarjeta de crédito');
  await expect(page.locator('#root-app')).toHaveText(/Tu tarjeta rechazó el pago/i);
});

test('test credit card payment test with pending status and logged in user', async ({ page }) => {
  await fillStepsToCheckout(page, url, loggedUserMLU);
  await payChoProRedirectLoggedTarjeta(page, PENDING.master, PENDING.form, loggedUserMLU, 'Tarjeta de crédito');
  await expect(page.locator('#root-app')).toHaveText(/Estamos procesando tu pago/i);
});

test('test credit card payment with approved and guest user', async ({ page }) => {
  await fillStepsToCheckout(page, url, guestUser);
  await payChoProRedirectGuestTarjeta(page, APPROVED.master, APPROVED.form, 'Tarjeta de crédito');
  await returnToCongratsPageRedirect(page);
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
});

test('test credit card payment with rejected and guest user', async ({ page }) => {
  await fillStepsToCheckout(page, url, guestUser);
  await payChoProRedirectGuestTarjeta(page, REJECTED.master, REJECTED.form, 'Tarjeta de crédito');
  await expect(page.locator('#root-app')).toHaveText(/Tu tarjeta rechazó el pago/i);
});

test('test credit card payment with pending and guest user', async ({ page }) => {
  await fillStepsToCheckout(page, url, guestUser);
  await payChoProRedirectGuestTarjeta(page, PENDING.master, PENDING.form, 'Tarjeta de crédito');
  await expect(page.locator('#root-app')).toHaveText(/Estamos procesando tu pago/i);
});