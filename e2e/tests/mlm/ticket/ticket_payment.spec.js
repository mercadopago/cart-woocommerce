import { test, expect } from "@playwright/test";
import { mlm } from "../../../data/meli_sites";
import { fillStepsToCheckout } from "../../../flows/fill_steps_to_checkout";
import payWithInvoice from "../../../flows/mlb/pay_with_invoice";

const{ url, guestUserDefault } = mlm;

test('Given a guest user, When they complete a payment with invoice, Should show success page and payment approval', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUserDefault);
  await payWithInvoice(page, guestUserDefault);

  await expect(page.locator('#submit-payment')).toContainText('Print ticket');
  await expect(page.getByText('Thank you. Your order has')).toBeVisible();
  await expect(page.getByText('Great, we processed your')).toBeVisible();
})
