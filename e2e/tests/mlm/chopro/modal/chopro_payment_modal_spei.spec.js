import { test, expect } from '@playwright/test';
import { mlm } from '../../../../data/meli_sites';
import { fillStepsToCheckout } from '../../../../flows/fill_steps_to_checkout';
import {
  select_cho_pro,
  cho_pro_modal_guest,
  cho_pro_modal_logged,
  cho_pro_modal_login,
  return_to_congrats_page
} from '../../../../flows/mlm/pay_with_cho_pro';

const { shop_url, guestUserMLM, loggedUserMLM } = mlm;

test('test guest', async ({ page }) => {
  await fillStepsToCheckout(page, shop_url, guestUserMLM);
  await select_cho_pro(page);
  await cho_pro_modal_guest(page, 'Transferencia SPEI Desde tu');
  await page.waitForLoadState();
  await return_to_congrats_page(page);
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
});

test('test logged', async ({ page }) => {
  await fillStepsToCheckout(page, shop_url, loggedUserMLM);
  await select_cho_pro(page);
  await cho_pro_modal_login(page, loggedUserMLM);
  await cho_pro_modal_logged(page, 'Transferencia SPEI Desde tu');
  await page.waitForLoadState();
  await return_to_congrats_page(page);
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
});
