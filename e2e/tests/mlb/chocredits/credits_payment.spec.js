import { test, expect } from '@playwright/test';
import { mlb } from "../../../data/meli_sites";
import { fillStepsToCheckout } from "../../../flows/fill_steps_to_checkout";
import payWithChoCredits from "../../../flows/mlb/pay_with_cho_credits";

const{ url, choCreditsUserMLB } = mlb;

test('test successful payment with pre approved credit, payment must be approved and success page must be shown', async ({ page }) => {
  await fillStepsToCheckout(page, url, choCreditsUserMLB);
  await payWithChoCredits(page, choCreditsUserMLB);

  await page.waitForURL('**/congrats/**');
  await page.getByRole('link', { name: 'Voltar para o site' }).click();
  await expect(page.locator('#main')).toHaveText(/Order received/i);
});
