import { test, expect } from "@playwright/test";
import { mlu } from "../../../data/meli_sites";
import { fillStepsToCheckout } from "../../../flows/fill_steps_to_checkout";
import { payWithInvoice } from "../../../flows/mlu/pay_with_invoice";

const { url, guestUserMLU } = mlu;

test('test invoice place order Abitab', async ({ page }) => {
  await fillStepsToCheckout(page, url, guestUserMLU);
  await page.waitForLoadState();
  await payWithInvoice(page, 'Abitab', guestUserMLU);
  await page.waitForLoadState();
  await expect(page.getByText('Thank you. Your order has')).toBeVisible();
  await expect(page.getByText('Great, we processed your')).toBeVisible();
});

test('test invoice place order Redpagos', async ({ page }) => {
    await fillStepsToCheckout(page, url, guestUserMLU);
    await page.waitForLoadState();
    await payWithInvoice(page, 'Redpagos', guestUserMLU);
    await page.waitForLoadState();
    await expect(page.getByText('Thank you. Your order has')).toBeVisible();
    await expect(page.getByText('Great, we processed your')).toBeVisible();
});

test('test invoice with invalid amount', async ({ page }) => {
    await fillStepsToCheckout(page, url, guestUserMLU);
    await page.waitForLoadState();
    await payWithInvoice(page, 'Abitab', guestUserMLU);
    await expect(page.locator('.wc-block-components-notice-banner__content')).toContainText('Invalid transaction_amount');
});