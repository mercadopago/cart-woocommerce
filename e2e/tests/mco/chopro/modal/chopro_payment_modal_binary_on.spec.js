import { test } from "@playwright/test";
import { mco } from "../../../../data/meli_sites";
import { modalCancelOrderTest, modalRejectAndChangeMethodTest } from "../../../../flows/chopro";
const { skipIfNotSite } = require("../../../../helpers/site-guard");

const { shop_url, guestUserDefault } = mco;

test.beforeEach(() => {
  skipIfNotSite(test, 'MCO');
});

test('Given a guest user, When their payment with chopro is pending and binary is on, Should show other payment options on change payment method', async ({page}) => {
  await modalRejectAndChangeMethodTest({ page, url: shop_url, user: guestUserDefault });
})

test('Given a guest user, When their payment with chopro is pending and binary is on and they close the modal, Should show the cancelled order message', async ({page}) => {
  await modalCancelOrderTest({ page, url: shop_url, user: guestUserDefault });
})
