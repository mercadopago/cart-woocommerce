import { test } from "@playwright/test";
import { mla } from "../../../../data/meli_sites";
import { redirectCancelOrderTest, redirectRejectAndChangeMethodTest } from "../../../../flows/chopro";
const { skipIfNotSite } = require("../../../../helpers/site-guard");

const { shop_url, guestUserDefault } = mla;

test.beforeEach(() => {
  skipIfNotSite(test, 'MLA');
});

test('test pending payment with chopro, binary must be on, payment must be rejected and cancelled order page must be shown', async ({ page }) => {
  await redirectCancelOrderTest({ page, url: shop_url, user: guestUserDefault });
})

test('test pending payment with chopro, payment must be rejected and other payment options must be shown', async ({ page }) => {
  await redirectRejectAndChangeMethodTest({ page, url: shop_url, user: guestUserDefault });
})
