import { test, expect } from "@playwright/test";
import { mlb } from "../../../data/meli_sites";
import fillStepsToCheckout from "../../../flows/fill_steps_to_checkout";
import payWithInvoice from "../../../flows/mlb/pay_with_invoice";

const{ url, guestUserMLB } = mlb;

test('test successful payment with invoice, payment must be approved and success page must be shown', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUserMLB);
  await payWithInvoice(page, guestUserMLB);

  await expect(page.locator('#submit-payment')).toContainText('Print ticket');
  await expect(page.getByText('Thank you. Your order has')).toBeVisible();
  await expect(page.getByText('Great, we processed your')).toBeVisible();
})
