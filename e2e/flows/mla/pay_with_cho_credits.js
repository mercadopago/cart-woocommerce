export default async function (page, user) {
  await page.getByLabel('Installments without cards').check();

  await page.waitForTimeout(2000);
  await page.locator('.wc-block-components-button').click();

  await page.waitForURL('**/checkout/v1/payment/**');

  await page.waitForURL('**/login/**');
  await page.getByTestId('user_id').fill(user.mpUserAccount);
  await page.getByRole('button', { name: 'Continuar' }).click();
  await page.getByTestId('password').fill(user.mpPasswordAccount);
  await page.getByTestId('action-complete').click();
  await page.waitForLoadState();

  await page.locator('#installments_select_credits-trigger').click();
  await page.waitForLoadState();
  await page.click('#installments_select_credits-menu-list li:first-child');
  await page.waitForLoadState();
  await page.getByRole('button', { name: 'Pagar' }).click();
  }
  