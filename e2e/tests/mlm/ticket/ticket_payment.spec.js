import { test, expect } from "@playwright/test";
import { mlm } from "../../../data/meli_sites";
import { successfulPaymentTest } from "../../../flows/ticket";

const { shop_url, guestUserDefault } = mlm;

test('Given a guest user, When they complete a payment with invoice, Should show success page and payment approval', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, guestUserDefault);
})
