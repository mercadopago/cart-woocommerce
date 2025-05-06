import { test, expect } from "@playwright/test";
import { mco } from "../../../../data/meli_sites";
import { fillStepsToCheckout } from "../../../../flows/fill_steps_to_checkout";
import { choproRedirect } from "../../../../flows/mco/pay_with_cho_pro";

const { url, credit_card_scenarios, guestUserDefault } = mco;
const { PENDING } = credit_card_scenarios;

test('Given a guest user, When their payment with chopro is pending and binary is on, Should show the decline message', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUserDefault);
  await choproRedirect(page, PENDING.masterMCO, PENDING.form);

  const returnButton = page.locator('.group-back-url a');
  await expect(returnButton).toBeVisible();

  returnButton.click();

  await page.waitForTimeout(3000);
  await expect(page.locator('.woocommerce-info')).toHaveText(/Your order was cancelled./i);
})

test('Given a guest user, When their payment with chopro is pending and binary is on, Should show other payment options', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUserDefault);
  await choproRedirect(page, PENDING.masterMCO, PENDING.form);

  const changePaymentMethod = page.locator('#group_card_ui').getByRole('button', { name: 'Pagar con otro medio' });
  await expect(changePaymentMethod).toBeVisible();

  changePaymentMethod.click();

  await page.waitForTimeout(3000);
  await expect(page.locator('#root-app')).toHaveText(/¿Cómo quieres pagar?/i);
})