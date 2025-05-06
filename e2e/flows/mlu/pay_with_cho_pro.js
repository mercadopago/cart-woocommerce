export const payChoProRedirectLoggedTarjeta = async function (page, card, form, user, method) {
  await page.waitForLoadState();
  await selectChoPro(page);
  await choProRedirectLogin(page, user);
  await choProRedirectLogged(page, method);
  await selectCreditCardAndFillDataRedirect(page, card, form);
  await mpInstallmentsAndPaymentFlowRedirectTargeta(page);
}

export const payChoProRedirectGuestTarjeta = async function (page, card, form, method) {
  await page.waitForTimeout(3000);
  await selectChoPro(page);
  await choProRedirectGuest(page, method);
  await selectCreditCardAndFillDataRedirect(page, card, form);
  await mpInstallmentsAndPaymentFlowRedirectTargeta(page);
}

export const payChoProRedirectLoggedEfectivo = async function (page, user, docNumber, method, type) {
  await page.waitForLoadState();
  await selectChoPro(page);
  await choProRedirectLogin(page, user);
  await choProRedirectLogged(page, method);
  await selectEfectivoMethodRedirect(page, type, docNumber);
  await mpInstallmentsAndPaymentFlowRedirectEfectivo(page);
}

export const payChoProRedirectGuestEfectivo = async function (page, docNumber, method, type) {
  await page.waitForTimeout(3000);
  await selectChoPro(page);
  await choProRedirectGuest(page, method);
  await selectEfectivoMethodRedirect(page, type, docNumber);
  await mpInstallmentsAndPaymentFlowRedirectEfectivo(page);
}

async function choProRedirectGuest(page, method) {
  await page.waitForLoadState();
  if (await page.getByTestId('action:understood-button').isVisible()) {
    await page.getByTestId('action:understood-button').click();
  }
  await selectRedirectPaymentMethod(page, method);
}

async function choProRedirectLogin(page, user) {
  if (await page.getByTestId('action:understood-button').isVisible()) {
    await page.getByTestId('action:understood-button').click();
  }
  await page.getByRole('button', { name: 'Ingresar con mi cuenta' }).click();
  await page.getByTestId('user_id').fill(user.email);
  await page.getByRole('button', { name: 'Continuar' }).click();
  await page.waitForLoadState();
  await page.getByTestId('password').fill(user.password);
  await page.getByTestId('action-complete').click();
  await page.waitForLoadState();
}

async function choProRedirectLogged(page, method) {
  await page.waitForLoadState();
  if (page.getByRole('button', { name: 'Elegir otro medio de pago' }).isVisible()) {
    await page.getByRole('button', { name: 'Elegir otro medio de pago' }).click();
  }
  await selectRedirectPaymentMethod(page, method);
}

async function selectRedirectPaymentMethod(page, method) {
  await page.waitForLoadState();
  await page.waitForTimeout(2000);
  await page.getByRole('button', { name: method }).click();
  await page.waitForTimeout(1500);
  if (await page.locator('.andes-button--progress').isVisible()) {
    await page.locator('.andes-button--progress').click();
  }
  await page.waitForLoadState();
}

async function selectEfectivoMethodRedirect(page, methodType, docNumber) {
  await page.waitForLoadState();
  await page.getByRole('button', { name: methodType }).click();

  await page.waitForLoadState();
  await page.locator('#identification_number').click();
  await page.locator('#identification_number').fill(docNumber);
}

async function selectCreditCardAndFillDataRedirect(page, card, form) {
  await page.waitForLoadState();
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

export const returnToCongratsPageRedirect = async function (page) {
  await page.waitForLoadState();
  await page.waitForTimeout(3000);
  const returnButton = page.locator('#group_button_back_congrats');
  if (await returnButton.isVisible()) {
    await returnButton.click();
  } else {
    await page.locator('#mercadopago-checkout').contentFrame().getByRole('link', { name: 'Volver al sitio' })
  }
}

async function mpInstallmentsAndPaymentFlowRedirectTargeta(page) {
  await page.waitForTimeout(3000);
  await page.getByRole('button', { name: 'Continuar' }).click();

  await page.waitForLoadState();
  await page.waitForTimeout(3000);
  await page.getByRole('button', { name: 'Pagar' }).click();

  await page.waitForLoadState();
  await page.waitForTimeout(3000);
}

async function mpInstallmentsAndPaymentFlowRedirectEfectivo(page) {
  await page.waitForLoadState();
  await page.waitForTimeout(3000);
  await page.getByRole('button', { name: 'Pagar' }).click();

  await page.waitForLoadState();
  await page.waitForTimeout(3000);
}

export const payChoproModalLoggedTarjeta = async function (page, card, form, user, method) {
  await page.waitForTimeout(3000);
  await selectChoPro(page);
  await choProModalLogin(page, user);
  await choProModalLogged(page);
  await selectCreditCardAndFillDataModal(page, card, form, method);
  await mpInstallmentsAndPaymentFlowModalTarjeta(page);
}

export const payChoproModalGuestTarjeta = async function (page, card, form, method) {
  await page.waitForTimeout(3000);
  await selectChoPro(page);
  await choProModalGuest(page);
  await selectCreditCardAndFillDataModal(page, card, form, method);
  await mpInstallmentsAndPaymentFlowModalTarjeta(page);
}

export const payChoproModalLoggedEfectivo = async function (page, user, docNumber, method, type) {
  await page.waitForTimeout(3000);
  await selectChoPro(page);
  await choProModalLogin(page, user);
  await choProModalLogged(page);
  await selectEfectivoMethodModal(page, docNumber, method, type);
  await mpInstallmentsAndPaymentFlowModalEfectivo(page);
}

export const payChoProModalGuestEfectivo = async function (page, docNumber, method, type) {
  await page.waitForTimeout(3000);
  await selectChoPro(page);
  await choProModalGuest(page);
  await selectEfectivoMethodModal(page, docNumber, method, type);
  await mpInstallmentsAndPaymentFlowModalEfectivo(page);
}

async function choProModalGuest(page) {
  await page.waitForLoadState();
  if (await page.getByTestId('action:understood-button').isVisible()) {
    await page.getByTestId('action:understood-button').click();
  }
}

async function choProModalLogin(page, user) {
  if (await page.locator('#mercadopago-checkout').contentFrame().getByTestId('action:understood-button').isVisible()) {
    await page.locator('#mercadopago-checkout').contentFrame().getByTestId('action:understood-button').click();
  }

  const popUpPromisse = page.waitForEvent('popup');
  await page.locator('#mercadopago-checkout').contentFrame().getByRole('button', { name: 'Ingresar con mi cuenta' }).click();
  const modal = await popUpPromisse;
  await modal.getByTestId('user_id').fill(user.email);
  await modal.getByRole('button', { name: 'Continuar' }).click();
  await modal.waitForLoadState();
  await modal.getByTestId('password').fill(user.password);
  await modal.getByTestId('action-complete').click();

  await page.waitForLoadState();
  await page.waitForTimeout(2000);
}

async function choProModalLogged(page) {
  await page.waitForLoadState();
  const frame = await page.locator('#mercadopago-checkout').contentFrame();
  if (frame.getByRole('button', { name: 'Elegir otro medio de pago' }).isVisible()) {
    await frame.getByRole('button', { name: 'Elegir otro medio de pago' }).click();
  }
}

async function selectCreditCardAndFillDataModal(page, card, form, method) {
  await page.waitForLoadState();
  const modal = await page.locator('#mercadopago-checkout').contentFrame();

  await page.waitForTimeout(3000);
  await modal.getByRole('button', { name: method }).click();

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

async function selectEfectivoMethodModal(page, docNumber, method, type) {
  await page.waitForLoadState();
  const modal = await page.locator('#mercadopago-checkout').contentFrame();

  await page.waitForTimeout(3000);
  await modal.getByRole('button', { name: method }).click();

  await page.waitForLoadState();
  if (await modal.getByTestId('action:understood-button').isVisible()) {
    await modal.getByTestId('action:understood-button').click();
  }

  await page.waitForTimeout(3000);
  await modal.getByRole('button', { name: type }).click();
  await page.waitForLoadState();

  await modal.getByLabel('Número').click();
  await modal.getByLabel('Número').fill(docNumber);
  await page.waitForLoadState();
}

async function mpInstallmentsAndPaymentFlowModalTarjeta(page) {
  const modal = page.locator('#mercadopago-checkout').contentFrame();
  await modal.getByRole('button', { name: 'Continuar' }).click();

  await page.waitForLoadState();
  await modal.locator('#pay').click();

  await page.waitForLoadState();
}

async function mpInstallmentsAndPaymentFlowModalEfectivo(page) {
  const modal = page.locator('#mercadopago-checkout').contentFrame();
  await modal.getByRole('button', { name: 'Pagar' }).click();
  await page.waitForLoadState();
}

export const returnToCongratsPageModal = async function (page) {
  const modal = page.locator('#mercadopago-checkout').contentFrame();
  await page.waitForLoadState();
  const returnButton = modal.locator('#group_button_back_congrats');
  if (await returnButton.isVisible()) {
    await returnButton.click();
  } else {
    await modal.locator('#mercadopago-checkout').contentFrame().getByRole('link', { name: 'Volver al sitio' })
  }
}

async function selectChoPro(page) {
  await page.getByText('Your saved cards or money').click();
  await page.waitForTimeout(4000);
  await page.waitForLoadState();
  await page.getByRole('button', { name: 'Place Order' }).click();
  await page.waitForTimeout(4000);
  await page.waitForLoadState();
}
