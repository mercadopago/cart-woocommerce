import { test, expect } from '@playwright/test';
import { mlb } from "../../../data/meli_sites";
import { fillStepsToCheckout } from "../../../flows/fill_steps_to_checkout";
import { payWithInvoice } from "../../../flows/mla/pay_with_invoice";

const { url, guestUserMLB } = mlb;

test('test invoice place order pago facil', async ({ page }) => {
  await fillStepsToCheckout(page, url, guestUserMLB);
  await payWithInvoice(page, 'Pago Fácil');
});

test('test invoice place order rapipago', async ({ page }) => {
  await fillStepsToCheckout(page, url, guestUserMLB);
  await payWithInvoice(page, 'Rapipago');
});

test('test invoice with invalid amount', async ({ page }) => {
  await fillStepsToCheckout(page, url, guestUserMLB);
  await payWithInvoice(page, 'Pago Fácil');
  await expect(page.locator('.wc-block-components-notice-banner__content')).toContainText('Invalid transaction_amount');
});
