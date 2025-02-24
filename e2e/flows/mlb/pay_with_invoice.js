import fill_invoice_data from "./fill_invoice_data";

export default async function(page, user) {
  await page.waitForTimeout(3000);
  await page.getByLabel('Invoice').check();
  await fill_invoice_data(page, user);

  // place order
  await page.locator('.wc-block-components-checkout-place-order-button', { hasText: 'Place Order' }).click();

  await page.waitForTimeout(3000);
}
