import { test } from "@playwright/test";
import { mlm } from "../../../data/meli_sites";
import { successfulPaymentTest } from "../../../flows/ticket";
const { skipIfNotSite } = require("../../../helpers/site-guard");

const { shop_url, guestUserMLM } = mlm;

test.beforeEach(() => {
  skipIfNotSite(test, 'MLM');
});

test('Given a guest user, When they complete a payment with invoice, Should show success page and payment approval', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, guestUserMLM);
})
