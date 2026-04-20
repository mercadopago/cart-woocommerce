import { test } from "@playwright/test";
import { mlb } from "../../../../data/meli_sites";
import { redirectCancelOrderTest } from "../../../../flows/chopro";

const { shop_url, guestUserMLB } = mlb;

test('test pending payment with chopro, binary must be on, payment must be rejected and decline message must be shown', async ({ page }) => {
  await redirectCancelOrderTest({ page, url: shop_url, user: guestUserMLB });
})
