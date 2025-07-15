import { test, expect } from "@playwright/test";
import { mco } from "../../../../data/meli_sites";
import { fillStepsToCheckout } from "../../../../flows/fill_steps_to_checkout";
import { choproModal } from "../../../../flows/mco/pay_with_cho_pro";

const { shop_url, credit_card_scenarios, guestUserDefault } = mco;
const { PENDING } = credit_card_scenarios;

test('Given a guest user, When their payment with chopro is pending and binary is on, Should show other payment options on change payment method', async ({page}) => {
  const modal = page.locator('#mercadopago-checkout').contentFrame();

  await fillStepsToCheckout(page, shop_url, guestUserDefault);
  await choproModal(page, PENDING.masterMCO, PENDING.form);

  const changePaymentMethod = modal.locator('#change_payment_method');
  await expect(changePaymentMethod).toBeVisible();
  await changePaymentMethod.click();

  await page.waitForTimeout(3000);
  await expect(modal.locator('#root-app')).toHaveText(/¿Cómo quieres pagar?/i)
})

test('Given a guest user, When their payment with chopro is pending and binary is on and they close the modal, Should show the cancelled order message', async ({page}) => {
  const modal = page.locator('#mercadopago-checkout').contentFrame();

  await fillStepsToCheckout(page, shop_url, guestUserDefault);
  await choproModal(page, PENDING.masterMCO, PENDING.form);

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
