import { test, expect } from '@playwright/test';
import { mlm } from '../../../../data/meli_sites';
import { fillStepsToCheckout } from '../../../../flows/fill_steps_to_checkout';
import {
  select_cho_pro,
  cho_pro_redirect_login,
  cho_pro_redirect_guest,
  cho_pro_redirect_logged,
  return_to_congrats_page
} from '../../../../flows/mlm/pay_with_cho_pro';

const { url, guestUserMLM, loggedUserMLM } = mlm;

test('test SPEI payment with guest user', async ({ page }) => {
  await fillStepsToCheckout(page, url, guestUserMLM);
  await select_cho_pro(page);
  await cho_pro_redirect_guest(page, 'Transferencia SPEI Desde tu banca en línea.');
  await page.waitForTimeout(1000);
  await return_to_congrats_page(page);
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
});

test('test SPEI payment with logged user', async ({ page }) => {
  await fillStepsToCheckout(page, url, loggedUserMLM);
  await select_cho_pro(page);
  await cho_pro_redirect_login(page, loggedUserMLM);
  await cho_pro_redirect_logged(page, 'Transferencia SPEI Desde tu banca en línea.');
  await page.waitForTimeout(1000);
  await return_to_congrats_page(page);
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
});
