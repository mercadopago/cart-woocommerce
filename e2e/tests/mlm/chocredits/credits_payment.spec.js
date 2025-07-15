import { test } from '@playwright/test';
import { mlm } from "../../../data/meli_sites";
import { successfulPaymentTest } from '../../../flows/credits';

const { shop_url, choCreditsUserMLM } = mlm;

test('Given Credits payment When payer is able to use Should complete payment successfully', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, choCreditsUserMLM);
});
