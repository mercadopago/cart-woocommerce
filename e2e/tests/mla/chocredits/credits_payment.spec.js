import { test } from '@playwright/test';
import { mla } from "../../../data/meli_sites";
import { successfulPaymentTest } from '../../../flows/credits';

const { shop_url, choCreditsUserMLA } = mla;

test('Given Credits payment When payer is able to use Should complete payment successfully', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, choCreditsUserMLA);
});
