export default async function fillInvoiceCheckout(page, user) {
    await page.waitForLoadState();
    await page.getByRole('radio', { name: 'Invoice' }).check();
    await page.locator('#doc_type').selectOption(user.documentType);
    await page.locator('input[name="mercadopago_ticket\\[doc_number\\]"]').click();
    await page.locator('input[name="mercadopago_ticket\\[doc_number\\]"]').fill(user.document);
}