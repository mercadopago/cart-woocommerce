import { test } from '@playwright/test';
import { mlu } from '../../../../data/meli_sites';
import { modalRejectAndChangeMethodTest, modalSuccessfulPaymentTest, modalSuccessfulPendingPaymentTest } from '../../../../flows/chopro';
const { skipIfNotSite } = require("../../../../helpers/site-guard");

const { shop_url, guestUser } = mlu;

test.beforeEach(() => {
  skipIfNotSite(test, 'MLU');
});

test('test successful payment guest with chopro, payment must be approved and success page must be shown', async ({ page }) => {
  await modalSuccessfulPaymentTest({ page, url: shop_url, user: guestUser });
});

test('test rejected payment guest with chopro, payment must be rejected and , other payment options must be shown', async ({ page }) => {
  await modalRejectAndChangeMethodTest({ page, url: shop_url, user: guestUser });
});

test('test pending chopro payment with guest, binary must be off, payment must be approved and success page must be shown', async ({ page }) => {
  await modalSuccessfulPendingPaymentTest({ page, url: shop_url, user: guestUser });
});
