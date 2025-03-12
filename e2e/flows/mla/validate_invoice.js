export const validateInvoice = async (page, method) => {
  await expect(page.locator('#post-8 iframe').contentFrame().locator('#mercadopago-client')).toContainText('en una sucursal de ' + method);
  await expect(page.locator('#post-8')).toContainText('Order number:');
  await page.getByRole('listitem').filter({ hasText: 'Payment method: Invoice' }).click();
  await expect(page.locator('#post-8')).toContainText('Payment method: Invoice');
}