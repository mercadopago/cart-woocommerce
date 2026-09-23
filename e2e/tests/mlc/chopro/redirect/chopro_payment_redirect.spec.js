import { test } from '@playwright/test';
import { mlc } from '../../../../data/meli_sites';
import { redirectCancelOrderTest, redirectSuccessfulPaymentTest, redirectSuccessfulPendingPaymentTest } from '../../../../flows/chopro';
const { skipIfNotSite } = require("../../../../helpers/site-guard");

const { shop_url, guestUserDefault } = mlc;

test.beforeEach(() => {
  skipIfNotSite(test, 'MLC');
});

test('test credit card payment with approved and guest user', async ({ page }) => {
  await redirectSuccessfulPaymentTest({ page, url: shop_url, user: guestUserDefault });
});

test('test credit card payment with rejected and guest user', async ({ page }) => {
  await redirectCancelOrderTest({ page, url: shop_url, user: guestUserDefault });
});

test('test credit card payment with pending and guest user', async ({ page }) => {
  await redirectSuccessfulPendingPaymentTest({ page, url: shop_url, user: guestUserDefault });
});
