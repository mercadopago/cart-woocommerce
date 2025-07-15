import { test } from "@playwright/test";
import { mpe } from "../../../data/meli_sites";
import { successfulPaymentTest } from "../../../flows/ticket";

const { shop_url, ...guestUserMPE } = mpe;

test('Given a guest user, When they complete a payment with invoice, Should show success page and payment approval', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, guestUserMPE);
})
