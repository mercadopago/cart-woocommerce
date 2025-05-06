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

const { url, guestUserMLM, loggedUserMLM } = mlm;

test('test efecty 7-eleven payment with guest user', async ({ page }) => {
  await fillStepsToCheckout(page, url, guestUserMLM);
  await select_cho_pro(page);
  await cho_pro_modal_guest(page, 'Efectivo', '7-Eleven');
  await page.waitForTimeout(1000);
  await return_to_congrats_page(page);
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
});

test('test efecty 7-eleven payment with logged user', async ({ page }) => {
  await fillStepsToCheckout(page, url, loggedUserMLM);
  await select_cho_pro(page);
  await cho_pro_modal_login(page, loggedUserMLM);
  await page.waitForLoadState();
  await page.waitForTimeout(4000);
  await cho_pro_modal_logged(page, 'Efectivo', '7-Eleven');
  await page.waitForTimeout(1000);
  await return_to_congrats_page(page);
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
});
