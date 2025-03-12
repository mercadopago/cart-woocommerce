export default async function fillInvoiceCheckout(page) {
  await page.getByRole('radio', { name: 'Invoice' }).check();
  await page.getByText('Efecty').click();
}
