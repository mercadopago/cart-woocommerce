export default async function(page) {
  await page.waitForLoadState();
  await page.locator('#radio-control-wc-payment-method-options-woo-mercado-pago-pix').check();

  // place order
  await page.waitForTimeout(3000);
  await page.locator('.wc-block-components-checkout-place-order-button').click();

  await page.waitForLoadState();
}
