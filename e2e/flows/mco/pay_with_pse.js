import fill_pse_checkout from "./fill_pse_checkout";

export default async function(page, user) {
  await fill_pse_checkout(page, user);
  await page.waitForTimeout(2000);
  await page.getByRole('button', { name: 'Place Order' }).click();
}