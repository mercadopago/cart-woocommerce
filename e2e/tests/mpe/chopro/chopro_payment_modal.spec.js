import { test } from "@playwright/test";
import { mpe } from "../../../data/meli_sites";
import { modalSuccessfulPaymentTest } from "../../../flows/chopro";
const { skipIfNotSite } = require("../../../helpers/site-guard");

const { shop_url, guestUser } = mpe;

test.beforeEach(() => {
  skipIfNotSite(test, 'MPE');
});

test('Given a guest user, When they complete a successful payment with chopro, Should show the success page', async ({ page }) => {
  await modalSuccessfulPaymentTest({ page, url: shop_url, user: guestUser });
})
