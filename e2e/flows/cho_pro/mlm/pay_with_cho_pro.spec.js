export const select_cho_pro = async function (page) {
  await page.getByText('Your saved cards or money').click();
  await page.waitForTimeout(2000);
  await page.getByRole('button', { name: 'Place Order' }).click();
}

export const cho_pro_redirect_guest = async function (page, method) {
  await page.waitForLoadState();
  if (await page.getByTestId('action:understood-button').isVisible()) {
    await page.getByTestId('action:understood-button').click();
  }
  select_redirect_payment_method(page, method);
}

export const cho_pro_redirect_login = async function (page, user) {
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

export const cho_pro_redirect_logged = async function (page, method) {
  await page.waitForLoadState();
  await page.waitForTimeout(2000);
  if (page.getByRole('button', { name: 'Elegir otro medio de pago' }).isVisible()) {
    await page.getByRole('button', { name: 'Elegir otro medio de pago' }).click();
  }
  select_redirect_payment_method(page, method);
}

async function select_redirect_payment_method(page, method) {
  await page.waitForLoadState();
  await page.waitForTimeout(1000);
  await page.getByRole('button', { name: method }).click();
  await page.waitForTimeout(1500);
  if (await page.locator('.andes-button--progress').isVisible()) {
    await page.locator('.andes-button--progress').click();
  }
  await page.waitForLoadState();
}

export const select_redirect_payment_option = async function (page, method) {
  await page.waitForLoadState();
  await page.waitForTimeout(1000);
  await page.getByRole('button', { name: method }).click();
  await page.waitForTimeout(1500);
  if (await page.locator('.andes-button--progress').isVisible()) {
    await page.locator('.andes-button--progress').click();
  }
  await page.waitForLoadState();
}

export const wait_redirect_page_load = async function (page) {
  const responsePromise = page.waitForResponse(response => response.url().includes('preference'));
  await responsePromise;
}

export const return_to_congrats_page = async function (page) {
  const responsePromise = page.waitForResponse(response => response.url().includes('congrats'));
  await responsePromise;
  await page.waitForTimeout(2000);
  const returnButton = page.locator('#group_button_back_congrats');
  if (await returnButton.isVisible()) {
    await returnButton.click();
  } else {
    await page.locator('#mercadopago-checkout').contentFrame().getByRole('link', { name: 'Volver al sitio' })
  }
}

export const cho_pro_modal_guest = async function (page, method, option) {
  await page.waitForLoadState();
  if (await page.locator('#mercadopago-checkout').contentFrame().getByTestId('action:understood-button').isVisible()) {
    await page.locator('#mercadopago-checkout').contentFrame().getByTestId('action:understood-button').click();
  }
  await select_modal_payment_method(page, method, option);
}

export const cho_pro_modal_login = async function (page, user) {
  if (await page.locator('#mercadopago-checkout').contentFrame().getByTestId('action:understood-button').isVisible()) {
    await page.locator('#mercadopago-checkout').contentFrame().getByTestId('action:understood-button').click();
  }

  const page1Promise = page.waitForEvent('popup');
  await page.locator('#mercadopago-checkout').contentFrame().getByRole('button', { name: 'Ingresar con mi cuenta' }).click();
  const page1 = await page1Promise;
  await page1.getByTestId('user_id').fill(user.email);
  await page1.getByRole('button', { name: 'Continuar' }).click();
  await page.waitForLoadState();
  await page1.getByTestId('password').fill(user.password);
  await page1.getByTestId('action-complete').click();

  await page.waitForLoadState();
  await page.waitForTimeout(2000);
}

export const cho_pro_modal_logged = async function (page, method, option) {
  await page.waitForLoadState();
  await page.waitForTimeout(1000);
  const frame = await page.locator('#mercadopago-checkout').contentFrame();
  if (frame.getByRole('button', { name: 'Elegir otro medio de pago' }).isVisible()) {
    await frame.getByRole('button', { name: 'Elegir otro medio de pago' }).click();
  }
  await select_modal_payment_method(page, method, option);
  await page.waitForTimeout(1000);
}

async function select_modal_payment_method(page, method, option) {
  await page.waitForLoadState();
  const modalFrame = await page.locator('#mercadopago-checkout').contentFrame();
  await page.waitForTimeout(1000);
  await modalFrame.getByRole('button', { name: method }).click();
  await page.waitForTimeout(1000);
  if ( await modalFrame.locator('#pay').isVisible()) {
    await modalFrame.locator('#pay').click();
  } else {
    await modalFrame.getByRole('button', { name: option }).click();
    await modalFrame.locator('#pay').click();
  }
  await page.waitForTimeout(1000);
}
