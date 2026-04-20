import { test } from "@playwright/test";
import { mlb } from "../../../../data/meli_sites";
import { modalCancelOrderTest, modalSuccessfulPendingPaymentTest, modalSuccessfulPaymentTest, modalRejectAndChangeMethodTest } from "../../../../flows/chopro";

const { shop_url, guestUserMLB } = mlb;

test('test successful payment with chopro, payment must be approved and success page must be shown', async ({ page }) => {
  await modalSuccessfulPaymentTest({ page, url: shop_url, user: guestUserMLB });
})

test('test rejected payment with chopro, other payment options must be shown', async ({ page }) => {
  await modalRejectAndChangeMethodTest({ page, url: shop_url, user: guestUserMLB });
})

test('test rejected payment with chopro modal, close button clicked, cancelled order page must be shown', async ({ page }) => {
  await modalCancelOrderTest({ page, url: shop_url, user: guestUserMLB });
})

test('test pending payment with chopro, binary must be off, payment must be approved and success page must be shown', async ({ page }) => {
  await modalSuccessfulPendingPaymentTest({ page, url: shop_url, user: guestUserMLB });
})
