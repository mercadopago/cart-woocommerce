import { test } from '@playwright/test';
import { mlb } from "../../../data/meli_sites";
import { successfulPaymentTest } from '../../../flows/credits';

const{ shop_url, choCreditsUserMLB } = mlb;

test('test successful payment with pre approved credit, payment must be approved and success page must be shown', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, choCreditsUserMLB);
});
