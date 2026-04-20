import { test } from '@playwright/test';
import { mlm } from "../../../data/meli_sites";
import { successfulPaymentTest } from '../../../flows/credits';
const { skipIfNotSite } = require("../../../helpers/site-guard");

const { shop_url, guestUserMLM } = mlm;

test.beforeEach(() => {
  skipIfNotSite(test, 'MLM');
});

test('Given Credits payment When payer is able to use Should complete payment successfully', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, guestUserMLM);
});
