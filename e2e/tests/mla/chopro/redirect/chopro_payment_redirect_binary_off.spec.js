import { test, expect } from "@playwright/test";
import { mla } from "../../../../data/meli_sites";
import fillStepsToCheckout from "../../../../flows/fill_steps_to_checkout";
import {choproRedirect} from "../../../../flows/mla/pay_with_cho_pro";

const{ url, credit_card_scenarios, guestUserROLA } = mla;
const { APPROVED, REJECTED, PENDING } = credit_card_scenarios;

test('test sucessfull payment with chopro, payment must be approved and success page must be shown', async ({page}) => {
    await fillStepsToCheckout(page, url, guestUserROLA);
    await choproRedirect(page, APPROVED.master, APPROVED.form);

    const returnButton = page.locator('#group_button_back_congrats');
    await expect(returnButton).toBeVisible();

    returnButton.click();

    await page.waitForTimeout(3000);
    await expect(page.locator('#main')).toHaveText(/Order received/i);
})

test('test rejected payment with chopro, payment must be rejected and decline message must be shown', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUserROLA);
  await choproRedirect(page, REJECTED.master, REJECTED.form);

  const returnButton = page.locator('.group-back-url a');
  await expect(returnButton).toBeVisible();

  returnButton.click();

  await page.waitForTimeout(3000);
  await expect(page.locator('.woocommerce-info')).toHaveText(/Your order was cancelled./i);
})

test('test rejected payment with chopro, payment must be rejected and other paymnt options must be shown', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUserROLA);
  await choproRedirect(page, REJECTED.master, REJECTED.form);

  const changePaymentMethod = page.locator('#group_card_ui').getByRole('button', { name: 'Pagar con otro medio' });
  await expect(changePaymentMethod).toBeVisible();

  changePaymentMethod.click();

  await page.waitForTimeout(3000);
  await expect(page.locator('#root-app')).toHaveText(/¿Cómo querés pagar?/i);
})

test('test pending payment with chopro, binary must be off, payment must be approved and success page must be shown', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUserROLA);
  await choproRedirect(page, PENDING.master, PENDING.form);

  const returnButton = page.locator('#button');
  await expect(returnButton).toBeVisible();

  returnButton.click();

  await page.waitForTimeout(3000);
  await expect(page.locator('#main')).toHaveText(/Order received/i);
})
