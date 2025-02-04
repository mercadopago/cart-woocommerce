export default async function(page) {
  await page.waitForTimeout(2000);

  // take to shop page
  await page.getByRole('link', { name: 'Shop' }).first().click();
  await page.waitForTimeout(2000);

  // choose first item
  await page.locator('main#main ul li a').first().click();
  await page.waitForTimeout(2000);

  // add to cart, take to checkout
  await page.getByRole('button', { name: 'Add to cart' }).click();
  await page.waitForTimeout(2000);
  await page.locator('#content').getByRole('link', { name: 'View cart' }).click();
  await page.getByRole('link', { name: 'Proceed to Checkout' }).click();
  await page.waitForTimeout(2000);
}
