import { test, expect } from '@playwright/test';
import { mlm } from "../../../data/meli_sites";
import { fillStepsToCheckout } from "../../../flows/fill_steps_to_checkout";
import payWithChoCredits from "../../../flows/mlm/pay_with_cho_credits";

const{ url, choCreditsUserMLM } = mlm;

test('Given Credits payment When payer is able to use Should complete payment successfully', async ({ page }) => {
  await fillStepsToCheckout(page, url, choCreditsUserMLM);

  await payWithChoCredits(page, choCreditsUserMLM);

  await page.getByRole('link', { name: 'Volver al sitio' }).click();
  await expect(page.locator('#main')).toHaveText(/Order received/i);
});
