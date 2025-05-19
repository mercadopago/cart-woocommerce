import { test, expect } from "@playwright/test";
import { mpe } from "../../../data/meli_sites";
import { fillStepsToCheckout } from "../../../flows/fill_steps_to_checkout";
import { choproRedirectGuestUser, choproRedirectLoggedUser } from "../../../flows/mpe/pay_with_cho_pro";

const { url, credit_card_scenarios, ...guestUserDefault } = mpe;
const { APPROVED } = credit_card_scenarios;

test('Given a guest user, When they complete a successful payment with chopro, Should show the success page', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUserDefault);
  await choproRedirectGuestUser(page, APPROVED.master, APPROVED.form, guestUserDefault);

  const returnButton = page.locator('#group_button_back_congrats');
  await expect(returnButton).toBeVisible();

  returnButton.click();

  await page.waitForTimeout(3000);
  await expect(page.locator('#main')).toHaveText(/Order received/i);
})

test('Given a logged user, When they complete a successful payment with chopro, Should show the success page', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUserDefault);
  await choproRedirectLoggedUser(page, APPROVED.master, APPROVED.form, guestUserDefault);

  const returnButton = page.locator('#group_button_back_congrats');
  await expect(returnButton).toBeVisible();

  returnButton.click();

  await page.waitForTimeout(3000);
  await expect(page.locator('#main')).toHaveText(/Order received/i);
})
