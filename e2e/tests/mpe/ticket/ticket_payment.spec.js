import { test } from "@playwright/test";
import { mpe } from "../../../data/meli_sites";
import { successfulPaymentTest } from "../../../flows/ticket";
const { skipIfNotSite } = require("../../../helpers/site-guard");

const { shop_url, guestUser } = mpe;

test.beforeEach(() => {
  skipIfNotSite(test, 'MPE');
});

test('Given a guest user, When they complete a payment with invoice, Should show success page and payment approval', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, guestUser);
})
