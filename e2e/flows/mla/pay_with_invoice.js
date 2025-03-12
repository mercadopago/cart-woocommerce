export const payWithInvoice = async (page, method) => {
  await page.waitForTimeout(400);
  await page.getByRole('radio', { name: 'Invoice' }).check();
  await page.getByText(method).click();
  await page.getByRole('button', { name: 'Place Order' }).click();
}
