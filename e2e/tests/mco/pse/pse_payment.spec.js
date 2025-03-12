import { test, expect } from '@playwright/test';
import { mco } from '../../../data/meli_sites';
import { fillStepsToCheckoutMulti } from '../../../flows/fill_steps_to_checkout';
import payWithPse from '../../../flows/mco/pay_with_pse';

const { url, pseUserMCO } = mco;

test('test pse approved payment', async ({ page }) => {
  await fillStepsToCheckoutMulti(page, url, pseUserMCO, '1000');
  await payWithPse(page, pseUserMCO);
  await page.waitForURL('**/bank_transfer?**');
});

test('test pse invalid amount', async ({ page }) => {
  await fillStepsToCheckoutMulti(page, url, pseUserMCO, '1');
  await payWithPse(page, pseUserMCO);
  await expect(page.locator('div').filter({ hasText: /^Invalid transaction_amount$/ }).nth(1)).toBeVisible();
});
