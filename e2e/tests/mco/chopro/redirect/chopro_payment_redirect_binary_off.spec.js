import { test, expect } from "@playwright/test";
import { mco } from "../../../../data/meli_sites";
import { fillStepsToCheckout } from "../../../../flows/fill_steps_to_checkout";
import { choproRedirect } from "../../../../flows/mco/pay_with_cho_pro";

const { url, credit_card_scenarios, guestUserDefault } = mco;
const { APPROVED, REJECTED, PENDING } = credit_card_scenarios;

test('Given a guest user, When they complete a successful payment with chopro, Should show the success page', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUserDefault);
  await choproRedirect(page, APPROVED.masterMCO, APPROVED.form);

  const returnButton = page.locator('#group_button_back_congrats');
  await expect(returnButton).toBeVisible();

  returnButton.click();

  await page.waitForTimeout(3000);
  await expect(page.locator('#main')).toHaveText(/Order received/i);
})

test('Given a guest user, When their payment with chopro is rejected, Should show the decline message', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUserDefault);
  await choproRedirect(page, REJECTED.masterMCO, REJECTED.form);

  const returnButton = page.locator('.group-back-url a');
  await expect(returnButton).toBeVisible();

  returnButton.click();

  await page.waitForTimeout(3000);
  await expect(page.locator('.woocommerce-info')).toHaveText(/Your order was cancelled./i);
})

test('Given a guest user, When their payment with chopro is rejected, Should show other payment options', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUserDefault);
  await choproRedirect(page, REJECTED.masterMCO, REJECTED.form);

  const changePaymentMethod = page.locator('#group_card_ui').getByRole('button', { name: 'Pagar con otro medio' });
  await expect(changePaymentMethod).toBeVisible();

  changePaymentMethod.click();

  await page.waitForTimeout(3000);
  await expect(page.locator('#root-app')).toHaveText(/¿Cómo quieres pagar?/i);
})

test('Given a guest user, When their payment with chopro is pending and binary is off, Should show the success page', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUserDefault);
  await choproRedirect(page, PENDING.masterMCO, PENDING.form);

  const returnButton = page.locator('#button');
  await expect(returnButton).toBeVisible();

  returnButton.click();

  await page.waitForTimeout(3000);
  await expect(page.locator('#main')).toHaveText(/Order received/i);
})