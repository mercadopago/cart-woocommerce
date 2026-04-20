export default async function(page) {
  await page.waitForLoadState();

  // Select PIX — supports both Classic and Blocks radio IDs
  const classicRadio = page.locator('#payment_method_woo-mercado-pago-pix');
  const blocksRadio = page.locator('#radio-control-wc-payment-method-options-woo-mercado-pago-pix');

  if (await classicRadio.isVisible({ timeout: 3000 }).catch(() => false)) {
    await page.locator('label[for="payment_method_woo-mercado-pago-pix"]').click();
  } else {
    await blocksRadio.check();
  }

  // Wait for Classic checkout's update_order_review AJAX to complete
  await page.waitForTimeout(2000);

  // Click place order — supports both Classic and Blocks
  const classicPlaceOrder = page.locator('#place_order');
  const blocksPlaceOrder = page.locator('.wc-block-components-checkout-place-order-button');

  if (await classicPlaceOrder.isVisible({ timeout: 3000 }).catch(() => false)) {
    await classicPlaceOrder.click();
  } else {
    await Promise.all([
      page.waitForResponse(
        resp => resp.url().includes('wc/store') && resp.url().includes('checkout') && resp.request().method() === 'POST',
        { timeout: 60000 }
      ),
      blocksPlaceOrder.click(),
    ]);
  }

  await page.waitForURL(/order-received/, { waitUntil: 'domcontentloaded', timeout: 30000 });
}
