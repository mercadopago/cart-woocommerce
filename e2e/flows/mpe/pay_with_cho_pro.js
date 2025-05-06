export const choproRedirectGuestUser = async function (page, card, form) {
  await page.waitForTimeout(3000);
  await selectChoProAndRedirect(page);
  await selectCreditCardAndFillData(page, card, form);
  await mpInstallmentsAndPaymentFlow(page);
}

export const choproRedirectLoggedUser = async function (page, card, form, user) {
  await page.waitForTimeout(3000);
  await selectChoProAndRedirect(page);
  await doLoginRedirect(page, user);
  await selectCreditCardAndFillData(page, card, form);
  await mpInstallmentsAndPaymentFlow(page);
}

export const choproModalGuestUser = async function (page, card, form, user) {
  await page.waitForTimeout(3000);
  await selectChoProAndRedirect(page);
  await selectCreditCardAndFillDataModal(page, card, form);
  await mpInstallmentsAndPaymentFlowModal(page);
}

export const choproModalLoggedUser = async function (page, card, form, user) {
  await page.waitForTimeout(3000);
  await selectChoProAndRedirect(page);
  await doLoginModal(page, user, modal);
  await selectCreditCardAndFillDataModal(page, card, form);
  await mpInstallmentsAndPaymentFlowModal(page);
}

async function doLoginModal(page, user) {
  await page.waitForLoadState();

  const modal = await page.locator('#mercadopago-checkout').contentFrame();
  const understoodButton = await page.locator('#mercadopago-checkout').contentFrame().getByTestId('action:understood-button');

  if (await understoodButton.isVisible()) await understoodButton.click();

  await modal.getByRole('button', { name: 'Ingresar con mi cuenta' }).click();
  const updatedModal = await page.waitForEvent('popup');

  await updatedModal.locator('#user_id').fill(user.mpUserAccount);
  await updatedModal.getByRole('button', {name: 'Continuar'}).click();
  await updatedModal.waitForLoadState();
  await updatedModal.locator('#password').fill(user.mpPasswordAccount);
  await updatedModal.getByRole('button', {name: 'Iniciar sesión'}).click();

  await page.waitForLoadState();

  const frame = await page.locator('#mercadopago-checkout').contentFrame();
  const paymentMethodButton = await frame.getByRole('button', { name: 'Elegir otro medio de pago' });
  
  if (paymentMethodButton.isVisible()) await paymentMethodButton.click();
}

async function doLoginRedirect(page, user) {
  await page.getByRole('button', {name: 'Ingresar con mi cuenta'}).click();
  await page.waitForLoadState();
  await page.locator('#user_id').fill(user.mpUserAccount);
  await page.getByRole('button', {name: 'Continuar'}).click();
  await page.waitForLoadState();
  await page.locator('#password').fill(user.mpPasswordAccount);
  await page.getByRole('button', {name: 'Iniciar sesión'}).click();
  await page.waitForLoadState();
  await page.getByRole('button', {name: 'Elegir otro medio de pago'}).click();
  await page.waitForLoadState();
}

async function selectChoProAndRedirect(page) {
  await page.getByLabel('Your saved cards or money available in Mercado Pago').check();
  await page.getByRole('button', { name: 'Place Order' }).click();
  await page.waitForLoadState();
}

async function selectCreditCardAndFillData(page, card, form) {
  await page.waitForLoadState();
  await page.locator('#other-options').getByRole('button', { name: 'Tarjeta de crédito' }).click();
  await page.waitForLoadState();
  await page.frameLocator('iframe[name="cardNumber"]').locator('[name="cardNumber"]').fill(card.number);
  await page.locator('#cardholderName').fill(form.name);
  await page.frameLocator('iframe[name="expirationDate"]').locator('[name="expirationDate"]').fill(card.date);
  await page.locator('#cardholderIdentificationNumber').fill(form.docNumber);
  await page.waitForTimeout(3000); // Wait card flag loading
  await page.frameLocator('iframe[name="securityCode"]').locator('[name="securityCode"]').fill(card.code);
}

async function mpInstallmentsAndPaymentFlow(page) {
  await page.waitForLoadState();
  await page.getByRole('button', { name: 'Continuar' }).click();

  await page.waitForLoadState();
  await page.click('ul li:first-child');

  await page.waitForLoadState();
  await page.getByRole('button', { name: 'Pagar' }).click();

  await page.waitForLoadState();
}

async function selectCreditCardAndFillDataModal(page, card, form) {
  await page.waitForLoadState();
  const modal = page.locator('#mercadopago-checkout').contentFrame();

  await page.waitForLoadState();
  await modal.getByRole('button', { name: 'Tarjeta de crédito' }).click();

  await page.waitForLoadState();
  await modal.frameLocator('iframe[name="cardNumber"]').locator('[name="cardNumber"]').fill(card.number);
  await modal.locator('#fullname').fill(form.name);
  await modal.frameLocator('iframe[name="expirationDate"]').locator('[name="expirationDate"]').fill(card.date);
  await modal.frameLocator('iframe[name="securityCode"]').locator('[name="securityCode"]').fill(card.code);

  await page.waitForLoadState();
  await modal.getByRole('button', { name: 'Continuar' }).click();
  await page.waitForLoadState();

  await modal.locator('#number').fill(form.docNumber);

  await page.waitForLoadState();
  await modal.getByRole('button', { name: 'Continuar' }).click();
}

async function mpInstallmentsAndPaymentFlowModal(page) {
  const modal = page.locator('#mercadopago-checkout').contentFrame();
  await page.waitForLoadState();
  await modal.locator('ul li:first-child').click();

  await page.waitForLoadState();
  await modal.getByRole('button', { name: 'Pagar' }).click();

  await page.waitForLoadState();
}
