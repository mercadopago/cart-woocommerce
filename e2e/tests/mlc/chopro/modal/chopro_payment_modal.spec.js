import { test } from '@playwright/test';
import { mlc } from '../../../../data/meli_sites';
import { modalRejectAndChangeMethodTest, modalSuccessfulPaymentTest, modalSuccessfulPendingPaymentTest } from '../../../../flows/chopro';
const { skipIfNotSite } = require("../../../../helpers/site-guard");

const { shop_url, guestUserDefault } = mlc;

test.beforeEach(() => {
  skipIfNotSite(test, 'MLC');
});

test('test successful payment guest with chopro, payment must be approved and success page must be shown', async ({ page }) => {
  await modalSuccessfulPaymentTest({ page, url: shop_url, user: guestUserDefault });
});

test('test rejected payment guest with chopro, payment must be rejected and decline message must be shown', async ({ page }) => {
  await modalRejectAndChangeMethodTest({ page, url: shop_url, user: guestUserDefault });
});

test('test pending payment guest with chopro, payment must be pending and the payment processing message must be shown', async ({ page }) => {
  await modalSuccessfulPendingPaymentTest({ page, url: shop_url, user: guestUserDefault });
});
