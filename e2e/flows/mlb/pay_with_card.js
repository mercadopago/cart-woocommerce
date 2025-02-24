export default async function(page, card, form) {
  await page.waitForTimeout(3000);
  await chooseCreditCardPayment(page);
  await page.waitForTimeout(1000);
  await fillCardForm(page, card);
  await page.waitForTimeout(3000);
  await fillInstallmentsAndAdditionalInfo(page, form);
  await placeOrder(page);
  await page.waitForTimeout(5000);
}

async function chooseCreditCardPayment(page) {
  await page.locator("#radio-control-wc-payment-method-options-woo-mercado-pago-custom").check();
}

async function fillCardForm(page, card) {
  // card fields
  await page.frameLocator('iframe[name="cardNumber"]').locator('[name="cardNumber"]').fill(card.number);
  await page.frameLocator('iframe[name="securityCode"]').locator('[name="securityCode"]').fill(card.code);
  await page.frameLocator('iframe[name="expirationDate"]').locator('[name="expirationDate"]').fill(card.date);
  await page.waitForTimeout(2000);
}

async function fillInstallmentsAndAdditionalInfo(page, form) {
  const installments = await page.locator('#mp-checkout-custom-installments')

  await page.waitForTimeout(2000);
  if(await installments.isVisible()) {
    // form fields
    await page.locator('#form-checkout__identificationType').selectOption(form.docType);
    await page.waitForTimeout(3000);
    await page.locator('[name="identificationNumber"]').fill(form.docNumber);
    await page.locator('#form-checkout__cardholderName').fill(form.name);

    // installments
    // otherwise emptyfield scenario will fail for empty fields scenario
    if(form.name !== ''){
      await page.locator('.mp-input-radio-container').first().click();
    }
    await page.waitForTimeout(1000);
  }
}

async function placeOrder(page){
  // place order
  await page.getByRole('button', { name: 'Place Order' }).click();
}
