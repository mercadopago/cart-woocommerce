import { test } from "@playwright/test";
import { mlu } from "../../../data/meli_sites";
import { rejectedPaymentTest, successfulPaymentTest } from "../../../flows/ticket";

const { shop_url, guestUserMLU } = mlu;

test('test invoice place order Abitab', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, guestUserMLU, 'Abitab');
});

test('test invoice place order Redpagos', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, guestUserMLU, 'Redpagos');
});

test('test invoice with invalid amount', async ({ page }) => {
  await rejectedPaymentTest(page, shop_url, guestUserMLU, 'Abitab');
});
