import { test, expect } from '@playwright/test';
import { mlm } from '../../../../data/meli_sites';
import { fillStepsToCheckoutMulti } from '../../../../flows/fill_steps_to_checkout';
import {
  select_cho_pro,
  cho_pro_modal_guest,
  cho_pro_modal_logged,
  cho_pro_modal_login,
  return_to_congrats_page
} from '../../../../flows/cho_pro/mlm/pay_with_cho_pro.spec';

const { url, guestUserMLM, loggedUserMLM } = mlm;

test('test efecty 7-eleven payment with guest user', async ({ page }) => {
  await fillStepsToCheckoutMulti(page, url, guestUserMLM, '2');
  await select_cho_pro(page);
  await cho_pro_modal_guest(page, 'Efectivo', '7-Eleven');
  await page.waitForTimeout(1000);
  await return_to_congrats_page(page);
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
});

test('test efecty 7-eleven payment with logged user', async ({ page }) => {
  await fillStepsToCheckoutMulti(page, url, loggedUserMLM, '2');
  await select_cho_pro(page);
  await cho_pro_modal_login(page, loggedUserMLM);
  await cho_pro_modal_logged(page, 'Efectivo', '7-Eleven');
  await page.waitForTimeout(1000);
  await return_to_congrats_page(page);
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
});
