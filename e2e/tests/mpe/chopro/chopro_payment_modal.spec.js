import { test, expect } from "@playwright/test";
import { mpe } from "../../../data/meli_sites";
import { fillStepsToCheckout } from "../../../flows/fill_steps_to_checkout";
import { choproModalGuestUser, choproModalLoggedUser } from "../../../flows/mpe/pay_with_cho_pro";

const { url, credit_card_scenarios, ...guestUserDefault } = mpe;
const { APPROVED } = credit_card_scenarios;

test('Given a guest user, When they complete a successful payment with chopro, Should show the success page', async ({page}) => {
  const modal = page.locator('#mercadopago-checkout').contentFrame();

  await fillStepsToCheckout(page, url, guestUserDefault);
  await choproModalGuestUser(page, APPROVED.master, APPROVED.form, guestUserDefault);

  const returnButton = modal.locator('#group_button_back_congrats');
  await expect(returnButton).toBeVisible();

  returnButton.click();

  await page.waitForTimeout(3000);
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
})

test('Given a logged user, When they complete a successful payment with chopro, Should show the success page', async ({page}) => {
  const modal = page.locator('#mercadopago-checkout').contentFrame();

  await fillStepsToCheckout(page, url, guestUserDefault);
  await choproModalLoggedUser(page, APPROVED.master, APPROVED.form, guestUserDefault);

  const returnButton = modal.locator('#group_button_back_congrats');
  await expect(returnButton).toBeVisible();

  returnButton.click();

  await page.waitForTimeout(3000);
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
})
