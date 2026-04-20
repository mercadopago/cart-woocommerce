import { test } from '@playwright/test';
import { mco } from '../../../data/meli_sites';
import { successfulPaymentTest } from '../../../flows/ticket';
const { skipIfNotSite } = require("../../../helpers/site-guard");

const { shop_url, guestUserMCO } = mco;

test.beforeEach(() => {
  skipIfNotSite(test, 'MCO');
});

test('test successful payment with invoice', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, guestUserMCO);
});
