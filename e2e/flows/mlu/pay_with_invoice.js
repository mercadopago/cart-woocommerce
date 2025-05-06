import fill_invoice_checkout from "./fill_invoice_checkout";

export const payWithInvoice = async (page, method, user) => {
    await page.waitForLoadState();
    await fill_invoice_checkout(page, user); 
    await page.getByText(method).click();
    await page.getByRole('button', { name: 'Place Order' }).click();
}