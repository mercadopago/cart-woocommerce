import { test, expect } from "@playwright/test";
import { mlb } from "../../../data/meli_sites";
import { fillStepsToCheckout } from "../../../flows/fill_steps_to_checkout";
import payWithPix from "../../../flows/pix";

const{ shop_url, guestUserMLB } = mlb;

test('test successful payment with Pix, payment must be approved and success page must be shown', async ({page}) => {
  await fillStepsToCheckout(page, shop_url, guestUserMLB);
  await payWithPix(page);

  // Payment processing and redirect to thank-you page can take up to 60s
  await expect(page.locator('.woocommerce-thankyou-order-received')).toBeVisible({ timeout: 60000 });
  await expect(page.locator('img.mp-details-pix-qr-img')).toBeVisible({ timeout: 10000 });
  await expect(page.locator('.mp-details-pix-qr-subtitle')).toBeVisible();
  await expect(page.locator('.mp-details-pix-qr-description')).toBeVisible();
  await expect(page.locator('#mp-qr-code')).toBeVisible();
})
