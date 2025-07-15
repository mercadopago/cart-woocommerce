export default async function fillPseCheckout(page, user) {
  await page.locator('#radio-control-wc-payment-method-options-woo-mercado-pago-pse').check();
  await page.locator('input[name="mercadopago_pse\\[docNumber\\]"]').fill(user.document);
  await page.waitForTimeout(2000);
}
