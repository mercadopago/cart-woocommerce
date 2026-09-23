import { test } from "@playwright/test";
import { mlm } from "../../../../data/meli_sites";
import { modalCancelOrderTest, modalSuccessfulPendingPaymentTest, modalSuccessfulPaymentTest, modalRejectAndChangeMethodTest } from "../../../../flows/chopro";
const { skipIfNotSite } = require("../../../../helpers/site-guard");

const { shop_url, guestUserMLM } = mlm;

test.beforeEach(() => {
  skipIfNotSite(test, 'MLM');
});

test('test sucessfull payment with chopro, payment must be approved and success page must be shown', async ({ page }) => {
  await modalSuccessfulPaymentTest({ page, url: shop_url, user: guestUserMLM });
})

test('test rejected payment with chopro, change payment method, other payment options must be shown', async ({ page }) => {
  await modalRejectAndChangeMethodTest({ page, url: shop_url, user: guestUserMLM });
})

test('test rejected payment with chopro modal, close button clicked, cancelled order message must be shown', async ({ page }) => {
  await modalCancelOrderTest({ page, url: shop_url, user: guestUserMLM });
})

test('test pending payment with chopro, binary must be off, payment must be approved and success page must be shown', async ({ page }) => {
  await modalSuccessfulPendingPaymentTest({ page, url: shop_url, user: guestUserMLM });
})
