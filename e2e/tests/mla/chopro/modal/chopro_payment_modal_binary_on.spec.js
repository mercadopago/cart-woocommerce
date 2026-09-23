import { test } from "@playwright/test";
import { mla } from "../../../../data/meli_sites";
import { modalCancelOrderTest, modalRejectAndChangeMethodTest } from "../../../../flows/chopro";
const { skipIfNotSite } = require("../../../../helpers/site-guard");

const { shop_url, guestUserDefault } = mla;

test.beforeEach(() => {
  skipIfNotSite(test, 'MLA');
});

test('test pending payment with chopro, binary must be on, other payment options must be show on change payment method', async ({page}) => {
  await modalRejectAndChangeMethodTest({ page, url: shop_url, user: guestUserDefault });
})

test('test pending payment with chopro modal - binary must be on, close button clicked, modal must be closed and order canceled message must be shown', async ({page}) => {
  await modalCancelOrderTest({ page, url: shop_url, user: guestUserDefault });
})
