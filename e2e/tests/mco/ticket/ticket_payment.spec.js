import { test, expect } from '@playwright/test';
import { mco } from '../../../data/meli_sites';
import { rejectedPaymentTest, successfulPaymentTest } from '../../../flows/ticket';

const { shop_url, guestUserMCO } = mco;

test('test successful payment with invoice', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, guestUserMCO);
});

test('test invoice invalid amount', async ({ page }) => {
  await rejectedPaymentTest(page, shop_url, guestUserMCO);
});
