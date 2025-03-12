import { test, expect } from "@playwright/test";
import { mlb } from "../../../data/meli_sites";
import { fillStepsToCheckout } from "../../../flows/fill_steps_to_checkout";
import payWithPix from "../../../flows/mlb/pay_with_pix";

const{ url, guestUserMLB } = mlb;

test('test successful payment with Pix, payment must be approved and success page must be shown', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUserMLB);
  await payWithPix(page);

  await expect(page.locator('#main')).toHaveText(/Order received/i);
  await expect(page.locator('.mp-details-pix-qr-description')).toContainText('If you prefer, you can pay by copying ');
  await expect(page.locator('.mp-details-pix-qr-subtitle')).toContainText('Code valid for');
})
