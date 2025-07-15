import { test, expect } from '@playwright/test';
import { mlm } from '../../../../data/meli_sites';
import { fillStepsToCheckout } from '../../../../flows/fill_steps_to_checkout';
import {
  select_cho_pro,
  cho_pro_modal_login,
  cho_pro_modal_logged,
  return_to_congrats_page
} from '../../../../flows/mlm/pay_with_cho_pro';

const { shop_url, loggedUserMLM } = mlm;

test('test payment with account money', async ({ page }) => {
  await fillStepsToCheckout(page, shop_url, loggedUserMLM);
  await select_cho_pro(page);
  await cho_pro_modal_login(page, loggedUserMLM);
  await cho_pro_modal_logged(page, 'Dinero disponible en Mercado');
  await page.waitForLoadState();
  await return_to_congrats_page(page);
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
});
