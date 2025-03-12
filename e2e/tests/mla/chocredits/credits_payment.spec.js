import { test, expect } from '@playwright/test';
import { mla } from "../../../data/meli_sites";
import { fillStepsToCheckout } from "../../../flows/fill_steps_to_checkout";
import payWithChoCredits from "../../../flows/mla/pay_with_cho_credits";

const{ url, choCreditsUserMLA } = mla;

test('Given Credits payment When payer is able to use Should complete payment successfully', async ({ page }) => {
  await fillStepsToCheckout(page, url, choCreditsUserMLA);

  await payWithChoCredits(page, choCreditsUserMLA);

  await page.waitForLoadState();
  await page.waitForURL('**/congrats/**');

  await page.waitForLoadState();
  await page.getByRole('link', { name: 'Volver al sitio' }).click();
  await expect(page.locator('#main')).toHaveText(/Order received/i);
});
