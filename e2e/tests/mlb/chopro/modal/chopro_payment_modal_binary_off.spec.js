import { test, expect } from "@playwright/test";
import { mlb } from "../../../../data/meli_sites";
import { fillStepsToCheckout } from "../../../../flows/fill_steps_to_checkout";
import { choproModal } from "../../../../flows/mlb/pay_with_cho_pro";

const{ url, credit_card_scenarios, guestUserMLB } = mlb;
const { APPROVED, REJECTED, PENDING } = credit_card_scenarios;

test('test sucessfull payment with chopro, payment must be approved and success page must be shown', async ({page}) => {
    const modal = page.locator('#mercadopago-checkout').contentFrame();

    await fillStepsToCheckout(page, url, guestUserMLB);
    await choproModal(page, APPROVED.master, APPROVED.formMLB);

    const returnButton = modal.locator('#group_button_back_congrats');
    await expect(returnButton).toBeVisible();

    returnButton.click();

    await page.waitForTimeout(3000);
    await expect(page.locator('#main')).toHaveText(/Order number:/i);
})

test('test rejected payment with chopro, change payment method, other payment options must be shown', async ({page}) => {
  const modal = page.locator('#mercadopago-checkout').contentFrame();

  await fillStepsToCheckout(page, url, guestUserMLB);
  await choproModal(page, REJECTED.master, REJECTED.formMLB);

  const changePaymentMethod = modal.locator('#change_payment_method');
  await expect(changePaymentMethod).toBeVisible();
  await changePaymentMethod.click();

  await page.waitForTimeout(3000);
  await expect(modal.locator('#root-app')).toHaveText(/Como você prefere pagar?/i);
})

test('test rejected payment with chopro modal, close button clicked, cancelled order message must be shown', async ({page}) => {
  const modal = page.locator('#mercadopago-checkout').contentFrame();

  await fillStepsToCheckout(page, url, guestUserMLB);
  await choproModal(page, REJECTED.master, REJECTED.formMLB);

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

test('test pending payment with chopro, binary must be off, payment must be approved and success page must be shown', async ({page}) => {
  const modal = page.locator('#mercadopago-checkout').contentFrame();

  await fillStepsToCheckout(page, url, guestUserMLB);
  await choproModal(page, PENDING.master, PENDING.formMLB);

  const returnButton = modal.locator('#button');
  await expect(returnButton).toBeVisible();

  returnButton.click();

  await page.waitForTimeout(3000);
  await expect(page.locator('#main')).toHaveText(/Order number:/i);
})

