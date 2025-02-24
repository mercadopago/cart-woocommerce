export default async function (page, user) {
  await page.waitForTimeout(3000);
  await page.getByLabel('Installments without cards').check();
  await page.getByRole('button', { name: 'Place Order' }).click();
  await page.waitForTimeout(3000);
  await page.getByTestId('user_id').fill(user.email);
  await page.getByRole('button', { name: 'Continuar' }).click();
  await page.getByTestId('password').fill(user.password);
  await page.getByTestId('action-complete').click();

  await page.waitForTimeout(3000);
  await page.locator('#installments_select_credits-trigger').click();
  await page.waitForTimeout(3000);
  await page.click('#installments_select_credits-menu-list li:first-child');
  await page.waitForTimeout(2000);
  await page.getByRole('button', { name: 'Pagar' }).click();
}
