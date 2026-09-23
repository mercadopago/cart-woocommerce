import { test } from "@playwright/test";
import { mla } from "../../../../data/meli_sites";
import { redirectCancelOrderTest, redirectSuccessfulPaymentTest, redirectRejectAndChangeMethodTest, redirectSuccessfulPendingPaymentTest } from "../../../../flows/chopro";
const { skipIfNotSite } = require("../../../../helpers/site-guard");

const { shop_url, guestUserDefault } = mla;

test.beforeEach(() => {
  skipIfNotSite(test, 'MLA');
});

test('test sucessfull payment with chopro, payment must be approved and success page must be shown', async ({ page }) => {
  await redirectSuccessfulPaymentTest({ page, url: shop_url, user: guestUserDefault });
})

test('test rejected payment with chopro, payment must be rejected and cancelled order page must be shown', async ({ page }) => {
  await redirectCancelOrderTest({ page, url: shop_url, user: guestUserDefault });
})

test('test rejected payment with chopro, payment must be rejected and other payment options must be shown', async ({ page }) => {
  await redirectRejectAndChangeMethodTest({ page, url: shop_url, user: guestUserDefault });
})

test('test pending payment with chopro, binary must be off, payment must be approved and success page must be shown', async ({ page }) => {
  await redirectSuccessfulPendingPaymentTest({ page, url: shop_url, user: guestUserDefault });
})
