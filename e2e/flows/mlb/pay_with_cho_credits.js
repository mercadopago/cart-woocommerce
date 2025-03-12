export default async function (page, user) {
  await page.waitForLoadState();
  await page.getByLabel('Installments without cards').check();
  await page.waitForTimeout(2000);
  await page.getByRole('button', { name: 'Place Order' }).click();
  await page.waitForLoadState();
  await page.getByTestId('user_id').fill(user.email);
  await page.getByRole('button', { name: 'Continuar' }).click();
  await page.getByTestId('password').fill(user.password);
  await page.getByTestId('action-complete').click();
  await page.waitForTimeout(1000);
  if (page.getByRole('button', { name: 'SMS Vamos enviar um código' }).isVisible()) {
    await page.getByRole('button', { name: 'SMS Vamos enviar um código' }).click();
    await page.waitForLoadState();
    await page.getByRole('textbox', { name: 'Dígito 1' }).click();
    await page.getByRole('textbox', { name: 'Dígito 1' }).fill(user.twoFactor);
    await page.getByRole('button', { name: 'Confirmar código' }).click();
  }

  await page.waitForLoadState();
  await page.locator('#installments_select_credits-trigger').click();
  await page.waitForLoadState();
  await page.click('#installments_select_credits-menu-list li:first-child');
  await page.waitForLoadState();
  await page.getByRole('button', { name: 'Pagar' }).click();

  await page.waitForLoadState();
  await page.waitForTimeout(1000);
  if (page.getByRole('button', { name: 'SMS No celular terminado em' }).isVisible()) {
    await page.getByRole('button', { name: 'SMS No celular terminado em' }).click();
    await page.waitForLoadState();
    await page.getByRole('textbox', { name: 'Dígito 1' }).click();
    await page.getByRole('textbox', { name: 'Dígito 1' }).fill(user.twoFactor);
    await page.getByRole('button', { name: 'Continuar' }).click();
  }
  await page.waitForLoadState();
}
