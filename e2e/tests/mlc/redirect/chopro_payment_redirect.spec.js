import { test, expect } from '@playwright/test';
import { mlc } from '../../../data/meli_sites';
import { fillStepsToCheckout } from '../../../flows/fill_steps_to_checkout';
import { payChoProRedirectLogged, returnToCongratsPage, payChoProRedirectGuest } from '../../../flows/mlc/pay_with_cho_pro';

const { url, credit_card_scenarios, loggedUserMLC } = mlc;
const { APPROVED, REJECTED, PENDING } = credit_card_scenarios;

test('test credit card payment test with approved status and logged in user', async ({ page }) => {
  await fillStepsToCheckout(page, url, loggedUserMLC);
  await payChoProRedirectLogged(page, APPROVED.master, APPROVED.form, loggedUserMLC, 'Tarjeta de crédito');
  await returnToCongratsPage(page);
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
});

test('test credit card payment test with rejected status and logged in user', async ({ page }) => {
  await fillStepsToCheckout(page, url, loggedUserMLC);
  await payChoProRedirectLogged(page, REJECTED.master, REJECTED.form, loggedUserMLC, 'Tarjeta de crédito');
  await expect(page.locator('#root-app')).toHaveText(/Tu tarjeta rechazó el pago/i);
});

test('test credit card payment test with pending status and logged in user', async ({ page }) => {
  await fillStepsToCheckout(page, url, loggedUserMLC);
  await payChoProRedirectLogged(page, PENDING.master, PENDING.form, loggedUserMLC, 'Tarjeta de crédito');
  await expect(page.locator('#root-app')).toHaveText(/Estamos procesando tu pago/i);
});

test('test credit card payment with approved and guest user', async ({ page }) => {
  await fillStepsToCheckout(page, url, loggedUserMLC);
  await payChoProRedirectGuest(page, APPROVED.master, APPROVED.form, 'Tarjeta de crédito');
  await returnToCongratsPage(page);
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
});

test('test credit card payment with rejected and guest user', async ({ page }) => {
  await fillStepsToCheckout(page, url, loggedUserMLC);
  await payChoProRedirectGuest(page, REJECTED.master, REJECTED.form, 'Tarjeta de crédito');
  await expect(page.locator('#root-app')).toHaveText(/Tu tarjeta rechazó el pago/i);
});

test('test credit card payment with pending and guest user', async ({ page }) => {
  await fillStepsToCheckout(page, url, loggedUserMLC);
  await payChoProRedirectGuest(page, PENDING.master, PENDING.form, 'Tarjeta de crédito');
  await expect(page.locator('#root-app')).toHaveText(/Estamos procesando tu pago/i);
});