import { test, expect } from '@playwright/test';
import { mco } from '../../../data/meli_sites';
import { fillStepsToCheckout } from '../../../flows/fill_steps_to_checkout';
import payWithPse from '../../../flows/mco/pay_with_pse';

const { shop_url, pseUserMCO } = mco;

test('test pse approved payment', async ({ page }) => {
  await fillStepsToCheckout(page, shop_url, pseUserMCO);
  await payWithPse(page, pseUserMCO);
  await page.waitForURL('**/bank_transfer?**');
  await page.waitForTimeout(2000);
  await page.waitForLoadState();
  await page.getByRole('button', { name: 'Approve' }).click();
  expect(page.locator('.woocommerce-thankyou-order-received')).toBeVisible();
});

test('test pse invalid amount', async ({ page }) => {
  await fillStepsToCheckout(page, shop_url, pseUserMCO);
  await payWithPse(page, pseUserMCO);
  await page.waitForTimeout(2000);
  expect(page.locator('.wc-block-store-notice.wc-block-components-notice-banner.is-error')).toBeVisible();
});
