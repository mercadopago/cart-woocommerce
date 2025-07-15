import { test } from "@playwright/test";
import { mlb } from "../../../data/meli_sites";
import { successfulPaymentTest } from "../../../flows/ticket";

const { shop_url, guestUserMLB } = mlb;

test('test successful payment with invoice, payment must be approved and success page must be shown', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, guestUserMLB);
})
