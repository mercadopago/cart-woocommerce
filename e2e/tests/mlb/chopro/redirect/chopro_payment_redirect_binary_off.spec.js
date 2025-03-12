import { test, expect } from "@playwright/test";
import { mlb } from "../../../../data/meli_sites";
import { fillStepsToCheckout } from "../../../../flows/fill_steps_to_checkout";
import { choproRedirect } from "../../../../flows/mlb/pay_with_cho_pro";

const{ url, credit_card_scenarios, guestUserMLB } = mlb;
const { APPROVED, REJECTED, PENDING } = credit_card_scenarios;

test('test sucessfull payment with chopro, payment must be approved and success page must be shown', async ({page}) => {
    await fillStepsToCheckout(page, url, guestUserMLB);
    await choproRedirect(page, APPROVED.master, APPROVED.formMLB);

    const returnButton = page.locator('#group_button_back_congrats');
    await expect(returnButton).toBeVisible();

    returnButton.click();

    await page.waitForTimeout(3000);
    await expect(page.locator('#main')).toHaveText(/Order received/i);
})

test('test rejected payment with chopro, payment must be rejected and decline message must be shown', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUserMLB);
  await choproRedirect(page, REJECTED.master, REJECTED.formMLB);

  const returnButton = page.locator('.group-back-url a');
  await expect(returnButton).toBeVisible();

  returnButton.click();

  await page.waitForTimeout(3000);
  await expect(page.locator('.woocommerce-info')).toHaveText(/Your order was cancelled./i);
})


test('test pending payment with chopro, binary must be off, payment must be approved and success page must be shown', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUserMLB);
  await choproRedirect(page, PENDING.master, PENDING.formMLB);

  const returnButton = page.locator('#button');
  await expect(returnButton).toBeVisible();

  returnButton.click();

  await page.waitForTimeout(3000);
  await expect(page.locator('#main')).toHaveText(/Order received/i);
})
