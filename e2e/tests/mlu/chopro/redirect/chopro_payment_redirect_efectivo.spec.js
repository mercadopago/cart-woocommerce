import { test, expect } from '@playwright/test';
import { mlu } from '../../../../data/meli_sites';
import { fillStepsToCheckout } from '../../../../flows/fill_steps_to_checkout';
import { payChoProRedirectLoggedEfectivo, returnToCongratsPageRedirect, payChoProRedirectGuestEfectivo } from '../../../../flows/mlu/pay_with_cho_pro';

const { shop_url, loggedUserMLU, guestUser } = mlu;

test('test efectivo payment abitab with approved status and logged in user', async ({ page }) => {
  await fillStepsToCheckout(page, shop_url, loggedUserMLU);
  await payChoProRedirectLoggedEfectivo(page, loggedUserMLU, loggedUserMLU.document, 'Efectivo', 'Abitab');
  await returnToCongratsPageRedirect(page);
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
});

test('test efectivo payment redpagos with approved status and logged in user', async ({ page }) => {
  await fillStepsToCheckout(page, shop_url, loggedUserMLU);
  await payChoProRedirectLoggedEfectivo(page, loggedUserMLU, loggedUserMLU.document, 'Efectivo', 'Redpagos');
  await returnToCongratsPageRedirect(page);
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
});

test('test efectivo payment abitab with approved and guest user', async ({ page }) => {
  await fillStepsToCheckout(page, shop_url, guestUser);
  await payChoProRedirectGuestEfectivo(page, guestUser.document, 'Efectivo', 'Abitab');
  await returnToCongratsPageRedirect(page);
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
});

test('test efectivo payment redpagos with approved and guest user', async ({ page }) => {
  await fillStepsToCheckout(page, shop_url, guestUser);
  await payChoProRedirectGuestEfectivo(page, guestUser.document, 'Efectivo', 'Redpagos');
  await returnToCongratsPageRedirect(page);
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
});
