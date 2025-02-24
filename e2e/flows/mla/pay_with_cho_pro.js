export const choproRedirect = async function(page, card, form) {
  await page.waitForTimeout(3000);
  await selectChoProAndRedirect(page);
  await selectCreditCardAndFillData(page, card, form);
  await mpInstallmentsAndPaymentFlow(page);
}

export const choproModal = async function(page, card, form) {
  await page.waitForTimeout(3000);
  await selectChoProAndRedirect(page);
  await selectCreditCardAndFillDataModal(page, card, form);
  await mpInstallmentsAndPaymentFlowModal(page);
}

async function selectChoProAndRedirect(page) {
  await page.getByLabel('Pay with the payment method you prefer').check();
  await page.getByRole('button', { name: 'Place Order' }).click();
}

async function selectCreditCardAndFillData(page, card, form) {
  await page.waitForTimeout(5000);
  await page.locator('#other-options').getByRole('button', { name: 'Tarjeta de crédito' }).click();
  await page.waitForTimeout(2000);
  await page.frameLocator('iframe[name="cardNumber"]').locator('[name="cardNumber"]').fill(card.number);
  await page.locator('#cardholderName').fill(form.name);
  await page.frameLocator('iframe[name="expirationDate"]').locator('[name="expirationDate"]').fill(card.date);

  await page.waitForTimeout(2000);
  await page.frameLocator('iframe[name="securityCode"]').locator('[name="securityCode"]').fill(card.code);
  await page.locator('#cardholderIdentificationNumber-dropdown').click();
  await page.getByTestId('popper').getByText('Otro').click();
  await page.locator('#cardholderIdentificationNumber').fill(form.docNumber);
}

async function mpInstallmentsAndPaymentFlow(page){
  await page.waitForTimeout(3000);
  await page.getByRole('button', { name: 'Continuar' }).click();

  await page.waitForTimeout(3000);
  await page.click('ul li:first-child');

  await page.waitForTimeout(3000);
  await page.getByRole('button', { name: 'Pagar' }).click();

  await page.waitForTimeout(3000);
}


async function selectCreditCardAndFillDataModal(page, card, form) {
  await page.waitForTimeout(5000);
  const modal = page.locator('#mercadopago-checkout').contentFrame();

  await page.waitForTimeout(3000);
  await modal.getByRole('button', { name: 'Tarjeta de crédito' }).click();

  await page.waitForTimeout(3000);
  await modal.frameLocator('iframe[name="cardNumber"]').locator('[name="cardNumber"]').fill(card.number);
  await modal.locator('#fullname').fill(form.name);
  await modal.frameLocator('iframe[name="expirationDate"]').locator('[name="expirationDate"]').fill(card.date);
  await modal.frameLocator('iframe[name="securityCode"]').locator('[name="securityCode"]').fill(card.code);

  await page.waitForTimeout(2000);
  await modal.getByRole('button', { name: 'Continuar' }).click();
  await page.waitForTimeout(2000);

  await modal.locator('#cow-select-trigger').click();
  await modal.getByRole('option', { name: 'Otro' }).click();
  await modal.locator('#number').fill(form.docNumber);
}

async function mpInstallmentsAndPaymentFlowModal(page){
  await page.waitForTimeout(5000);
  const modal = page.locator('#mercadopago-checkout').contentFrame();
  await modal.getByRole('button', { name: 'Continuar' }).click();

  await page.waitForTimeout(3000);
  await modal.locator('ul li:first-child').click();

  await page.waitForTimeout(3000);
  await modal.getByRole('button', { name: 'Pagar' }).click();

  await page.waitForTimeout(3000);
}
