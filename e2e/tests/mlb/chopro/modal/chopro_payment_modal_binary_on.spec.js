import { test } from "@playwright/test";
import { mlb } from "../../../../data/meli_sites";
import { modalCancelOrderTest, modalRejectAndChangeMethodTest } from "../../../../flows/chopro";

const { shop_url, guestUserMLB } = mlb;

test('test pending payment with chopro, binary must be on, other payment options must be show on change payment method', async ({ page }) => {
  await modalRejectAndChangeMethodTest({ page, url: shop_url, user: guestUserMLB });
})

test('test pending payment with chopro modal - binary must be on, close button clicked, modal must be closed and order canceled page must be shown', async ({ page }) => {
  await modalCancelOrderTest({ page, url: shop_url, user: guestUserMLB });
})
