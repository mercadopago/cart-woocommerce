import fill_invoice_checkout from "./fill_invoice_checkout";

export default async function(page) {
  await fill_invoice_checkout(page);
  await page.waitForTimeout(2000);
  await page.getByRole('button', { name: 'Place Order' }).click();
  await page.waitForLoadState();
}