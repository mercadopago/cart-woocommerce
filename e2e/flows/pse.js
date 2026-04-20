export default async function (page, user) {
  // Select PSE — supports both Classic and Blocks
  const classicRadio = page.locator('#payment_method_woo-mercado-pago-pse');
  const blocksRadio = page.locator('#radio-control-wc-payment-method-options-woo-mercado-pago-pse');

  if (await classicRadio.isVisible({ timeout: 3000 }).catch(() => false)) {
    await page.locator('label[for="payment_method_woo-mercado-pago-pse"]').click();
  } else {
    await blocksRadio.check();
  }

  await page.waitForTimeout(1000);

  // Use the Narciso input-document component's input ID — works for both Classic and Blocks.
  // Classic uses input-name="mercadopago_pse[doc_number]" (snake_case), Blocks uses
  // inputName="mercadopago_pse[docNumber]" (camelCase), but both share input-id.
  await page.locator('#mp-pse-gateway-document-input').fill(user.document);
  await page.waitForTimeout(2000);

  // Click place order — supports both Classic and Blocks
  const classicPlaceOrder = page.locator('#place_order');
  const blocksPlaceOrder = page.locator('.wc-block-components-checkout-place-order-button');

  if (await classicPlaceOrder.isVisible({ timeout: 3000 }).catch(() => false)) {
    await classicPlaceOrder.click();
  } else {
    await blocksPlaceOrder.click();
  }
}
