import { test, expect } from "@playwright/test";
import { mla } from "../../../../data/meli_sites";
import { fillStepsToCheckout } from "../../../../flows/fill_steps_to_checkout";
import { choproModal } from "../../../../flows/mla/pay_with_cho_pro";

const{ url, credit_card_scenarios, guestUserDefault } = mla;
const { APPROVED, REJECTED, PENDING } = credit_card_scenarios;

test('test sucessfull payment with chopro, payment must be approved and success page must be shown', async ({page}) => {
    const modal = page.locator('#mercadopago-checkout').contentFrame();

    await fillStepsToCheckout(page, url, guestUserDefault);
    await choproModal(page, APPROVED.master, APPROVED.form);

    const returnButton = modal.locator('#group_button_back_congrats');
    await expect(returnButton).toBeVisible();

    returnButton.click();

    await page.waitForTimeout(3000);
    await expect(page.locator('#main')).toHaveText(/Order number:/i);
})

test('test rejected payment with chopro, change payment method, other payment options must be shown', async ({page}) => {
  const modal = page.locator('#mercadopago-checkout').contentFrame();

  await fillStepsToCheckout(page, url, guestUserDefault);
  await choproModal(page, REJECTED.master, REJECTED.form);

  const changePaymentMethod = modal.locator('#change_payment_method');
  await expect(changePaymentMethod).toBeVisible();
  await changePaymentMethod.click();

  await page.waitForTimeout(3000);
  await expect(modal.locator('#root-app')).toHaveText(/¿Cómo querés pagar?/i);
})

test('test rejected payment with chopro modal, close button clicked, cancelled order message must be shown', async ({page}) => {
  const modal = page.locator('#mercadopago-checkout').contentFrame();

  await fillStepsToCheckout(page, url, guestUserDefault);
  await choproModal(page, REJECTED.master, REJECTED.form);

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

test('test pending payment with chopro, binary must be off, payment must be approved and success page must be shown', async ({page}) => {
  const modal = page.locator('#mercadopago-checkout').contentFrame();

  await fillStepsToCheckout(page, url, guestUserDefault);
  await choproModal(page, PENDING.master, PENDING.form);

  const returnButton = modal.locator('#button');
  await expect(returnButton).toBeVisible();

  returnButton.click();

  await page.waitForTimeout(3000);
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
})
