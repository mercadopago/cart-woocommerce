import { test } from '@playwright/test';
import { mla } from "../../../data/meli_sites";
import { successfulPaymentTest } from '../../../flows/credits';
const { skipIfNotSite } = require("../../../helpers/site-guard");

const { shop_url, guestUserMLA } = mla;

test.beforeEach(() => {
  skipIfNotSite(test, 'MLA');
});

test('Given Credits payment When payer is able to use Should complete payment successfully', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, guestUserMLA);
});
