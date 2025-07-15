import { test, expect } from '@playwright/test';
import { mlm } from '../../../../data/meli_sites';
import { fillStepsToCheckout } from '../../../../flows/fill_steps_to_checkout';
import {
  select_cho_pro,
  cho_pro_redirect_login,
  cho_pro_redirect_guest,
  cho_pro_redirect_logged,
  select_redirect_payment_option,
  wait_redirect_page_load,
  return_to_congrats_page
} from '../../../../flows/mlm/pay_with_cho_pro';

const { shop_url, guestUserMLM, loggedUserMLM } = mlm;

test('test efecty 7-eleven payment with guest user', async ({ page }) => {
  await fillStepsToCheckout(page, shop_url, guestUserMLM);
  await select_cho_pro(page);
  await page.waitForTimeout(1000);
  await wait_redirect_page_load(page);
  await cho_pro_redirect_guest(page, 'Efectivo');
  await page.waitForTimeout(1000);
  await expect(page.locator('h1')).toContainText('Dónde quieres pagar');
  await select_redirect_payment_option(page, '7-Eleven El pago se acreditar');
  await return_to_congrats_page(page);
  await page.waitForLoadState();
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
});

test('test efecty 7-Eleven payment with logged user', async ({ page }) => {
  await fillStepsToCheckout(page, shop_url, loggedUserMLM);
  await select_cho_pro(page);
  await page.waitForTimeout(1000);
  await wait_redirect_page_load(page);
  await cho_pro_redirect_login(page, loggedUserMLM);
  await cho_pro_redirect_logged(page, 'Efectivo');
  await page.waitForTimeout(1000);
  await expect(page.locator('h1')).toContainText('Dónde quieres pagar');
  await select_redirect_payment_option(page, '7-Eleven El pago se acreditar');
  await return_to_congrats_page(page);
  await page.waitForLoadState();
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
});
