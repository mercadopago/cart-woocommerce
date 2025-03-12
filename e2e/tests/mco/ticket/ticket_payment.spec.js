import { test, expect } from '@playwright/test';
import { mco } from '../../../data/meli_sites';
import { fillStepsToCheckoutMulti } from '../../../flows/fill_steps_to_checkout';
import payWithInvoice from '../../../flows/mco/pay_with_invoice';

const { url, guestUserMCO } = mco;

test('test successful payment with invoice', async ({ page }) => {
    await fillStepsToCheckoutMulti(page, url, guestUserMCO, '40');
    await payWithInvoice(page);
    
    await expect(page.locator('#submit-payment')).toContainText('Print ticket');
    await expect(page.getByText('Thank you. Your order has')).toBeVisible();
    await expect(page.getByText('Great, we processed your')).toBeVisible();
});

test('test invoice invalid amount', async ({ page }) => {
    await fillStepsToCheckoutMulti(page, url, guestUserMCO, '1');
    await payWithInvoice(page, guestUserMCO);
    
    await expect(page.locator('div').filter({ hasText: /^Invalid transaction_amount$/ }).nth(1)).toBeVisible();
});