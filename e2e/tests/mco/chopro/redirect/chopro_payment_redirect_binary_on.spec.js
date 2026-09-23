import { test } from "@playwright/test";
import { mco } from "../../../../data/meli_sites";
import { redirectCancelOrderTest, redirectRejectAndChangeMethodTest } from "../../../../flows/chopro";
const { skipIfNotSite } = require("../../../../helpers/site-guard");

const { shop_url, guestUserDefault } = mco;

test.beforeEach(() => {
  skipIfNotSite(test, 'MCO');
});

test('Given a guest user, When their payment with chopro is pending and binary is on, Should show the decline message', async ({page}) => {
  await redirectCancelOrderTest({ page, url: shop_url, user: guestUserDefault });
})

test('Given a guest user, When their payment with chopro is pending and binary is on, Should show other payment options', async ({page}) => {
  await redirectRejectAndChangeMethodTest({ page, url: shop_url, user: guestUserDefault });
})
