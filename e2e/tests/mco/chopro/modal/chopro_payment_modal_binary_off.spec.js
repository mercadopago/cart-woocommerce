import { test, expect } from "@playwright/test";
import { mco } from "../../../../data/meli_sites";
import { fillStepsToCheckout } from "../../../../flows/fill_steps_to_checkout";
import { choproModal } from "../../../../flows/mco/pay_with_cho_pro";

const{ url, credit_card_scenarios, guestUserDefault } = mco;
const { APPROVED, REJECTED, PENDING } = credit_card_scenarios;

test('Given a guest user, When they complete a successful payment with chopro, Should show the success page', async ({page}) => {
    const modal = page.locator('#mercadopago-checkout').contentFrame();

    await fillStepsToCheckout(page, url, guestUserDefault);
    await choproModal(page, APPROVED.masterMCO, APPROVED.formMCO);

    const returnButton = modal.locator('#group_button_back_congrats');
    await expect(returnButton).toBeVisible();

    returnButton.click();

    await page.waitForTimeout(3000);
    await expect(page.locator('#main')).toHaveText(/Order number:/i);
})

test('Given a guest user, When their payment with chopro is rejected, Should show other payment options', async ({page}) => {
  const modal = page.locator('#mercadopago-checkout').contentFrame();

  await fillStepsToCheckout(page, url, guestUserDefault);
  await choproModal(page, REJECTED.masterMCO, REJECTED.formMCO);

  const changePaymentMethod = modal.locator('#change_payment_method');
  await expect(changePaymentMethod).toBeVisible();
  await changePaymentMethod.click();

  await page.waitForTimeout(3000);
  await expect(modal.locator('#root-app')).toHaveText(/¿Cómo quieres pagar?/i);
})

test('Given a guest user, When their payment with chopro is rejected and they close the modal, Should show the cancelled order message', async ({page}) => {
  const modal = page.locator('#mercadopago-checkout').contentFrame();

  await fillStepsToCheckout(page, url, guestUserDefault);
  await choproModal(page, REJECTED.masterMCO, REJECTED.formMCO);

  const changePaymentMethod = modal.locator('#change_payment_method');
  const cancelPayment = modal.locator('#mp-close-btn');

  await expect(changePaymentMethod).toBeVisible();
  await cancelPayment.click();

  await page.waitForTimeout(3000);

  const closeAndCancel = modal.locator('.fullscreen-message__content').getByRole('button', { name: 'Cancelar pago' });
  await expect(closeAndCancel).toBeVisible();
  await closeAndCancel.click();

  await page.waitForTimeout(3000);
  await expect(page.locator('.woocommerce-info')).toHaveText(/Your order was cancelled./i);
})

test('Given a guest user, When their payment with chopro is pending and binary is off, Should show the success page', async ({page}) => {
  const modal = page.locator('#mercadopago-checkout').contentFrame();

  await fillStepsToCheckout(page, url, guestUserDefault);
  await choproModal(page, PENDING.masterMCO, PENDING.formMCO);

  const returnButton = modal.locator('#button');
  await expect(returnButton).toBeVisible();

  returnButton.click();

  await page.waitForTimeout(3000);
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
})
