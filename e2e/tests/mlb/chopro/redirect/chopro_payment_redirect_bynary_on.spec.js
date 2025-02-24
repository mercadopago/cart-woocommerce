import { test, expect } from "@playwright/test";
import { mlb } from "../../../../data/meli_sites";
import fillStepsToCheckout from "../../../../flows/fill_steps_to_checkout";
import {choproRedirect} from "../../../../flows/mlb/pay_with_cho_pro";

const{ url, credit_card_scenarios, guestUserMLB } = mlb;
const { PENDING } = credit_card_scenarios;

test('test pending payment with chopro, binary must be on, payment must be rejected and decline message must be shown', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUserMLB);
  await choproRedirect(page, PENDING.master, PENDING.form);

  const returnButton = page.locator('.group-back-url a');
  await expect(returnButton).toBeVisible();

  returnButton.click();

  await page.waitForTimeout(3000);
  await expect(page.locator('.woocommerce-info')).toHaveText(/Your order was cancelled./i);
})
