import { test } from "@playwright/test";
import { mlu } from "../../../data/meli_sites";
import { successfulPaymentTest } from "../../../flows/ticket";
const { skipIfNotSite } = require("../../../helpers/site-guard");

const { shop_url, guestUser } = mlu;

test.beforeEach(() => {
  skipIfNotSite(test, 'MLU');
});

test('test invoice place order Abitab', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, guestUser, 'Abitab');
});

test('test invoice place order Redpagos', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, guestUser, 'Redpagos');
});
