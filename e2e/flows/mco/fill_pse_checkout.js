export default async function fillPseCheckout(page, user) {
  await page.getByRole('radio', { name: 'PSE' }).check();
  await page.locator('input[name="mercadopago_pse\\[docNumber\\]"]').fill(user.document);
}
