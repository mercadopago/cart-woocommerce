import { test, expect } from "@playwright/test";
import { mlb } from "../../../../data/meli_sites";
import fillStepsToCheckout from "../../../../flows/fill_steps_to_checkout";
import {choproModal} from "../../../../flows/mlb/pay_with_cho_pro";

const{ url, credit_card_scenarios, guestUserMLB } = mlb;
const { PENDING } = credit_card_scenarios;

test('test pending payment with chopro, binary must be on, other payment options must be show on change payment method', async ({page}) => {
  const modal = page.locator('#mercadopago-checkout').contentFrame();

  await fillStepsToCheckout(page, url, guestUserMLB);
  await choproModal(page, PENDING.master, PENDING.form);

  const changePaymentMethod = modal.locator('#change_payment_method');
  await expect(changePaymentMethod).toBeVisible();
  await changePaymentMethod.click();

  await page.waitForTimeout(3000);
  await expect(modal.locator('#root-app')).toHaveText(/Como você prefere pagar?/i);
})

test('test pending payment with chopro modal - binary must be on, close button clicked, modal must be closed and order canceled message must be shown', async ({page}) => {
  const modal = page.locator('#mercadopago-checkout').contentFrame();

  await fillStepsToCheckout(page, url, guestUserMLB);
  await choproModal(page, PENDING.master, PENDING.form);

  const changePaymentMethod = modal.locator('#change_payment_method');
  const cancelPayment = modal.locator('#mp-close-btn');

  await expect(changePaymentMethod).toBeVisible();
  await cancelPayment.click();

  await page.waitForTimeout(3000);

  const closeAndCancel = modal.locator('.fullscreen-message__content').getByRole('button', { name: 'Fechar e cancelar pagamento' });
  await expect(closeAndCancel).toBeVisible();
  await closeAndCancel.click();

  await page.waitForTimeout(3000);
  await expect(page.locator('.woocommerce-info')).toHaveText(/Your order was cancelled./i);
})
