import { test, expect } from '@playwright/test';
import { mco } from '../../../data/meli_sites';
import { fillStepsToCheckout } from '../../../flows/fill_steps_to_checkout';
import payWithPse from '../../../flows/mco/pay_with_pse';

const { url, pseUserMCO } = mco;

test('test pse approved payment', async ({ page }) => {
  await fillStepsToCheckout(page, url, pseUserMCO);
  await payWithPse(page, pseUserMCO);
  await page.waitForURL('**/bank_transfer?**');
  await page.waitForTimeout(2000);
  await page.waitForLoadState();
  await page.getByRole('button', { name: 'Approve' }).click();
  await expect(page.getByText('Thank you. Your order has')).toBeVisible();
});

test('test pse invalid amount', async ({ page }) => {
  await fillStepsToCheckout(page, url, pseUserMCO);
  await payWithPse(page, pseUserMCO);
  await expect(page.locator('div').filter({ hasText: /^Invalid transaction_amount$/ }).nth(1)).toBeVisible();
});
