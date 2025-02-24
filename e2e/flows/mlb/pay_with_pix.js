export default async function(page, form) {
  await page.waitForTimeout(3000);
  await page.getByLabel('Pix').check();

  // place order
  await page.locator('.wc-block-components-checkout-place-order-button', { hasText: 'Place Order' }).click();

  await page.waitForTimeout(3000);
}
