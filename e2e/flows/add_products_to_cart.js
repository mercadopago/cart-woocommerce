export const addProductsToCart = async function(page) {
  // take to shop page
  await page.waitForLoadState();
  await page.getByRole('link', { name: 'Shop' }).first().click();

  // choose first item
  await page.waitForLoadState();
  await page.locator('#main .products li').first().click();

  // add to cart
  await page.waitForLoadState();
  await page.getByRole('button', { name: 'Add to cart' }).click();
  await page.waitForTimeout(1000);
  await page.locator('.woocommerce-message').getByRole('link', { name: 'View cart' }).click();

  // proceed to checkout
  await page.waitForLoadState();
  await page.getByRole('link', { name: 'Proceed to Checkout' }).click();
}

export const addMultipleProductsToCart = async function(page, quantity) {
  // take to shop page
  await page.waitForLoadState();
  await page.getByRole('link', { name: 'Shop' }).first().click();

  // choose first item
  await page.waitForLoadState();
  await page.locator('#main .products li').first().click();

  // add to cart
  await page.waitForLoadState();
  await page.getByRole('spinbutton', { name: 'Product quantity' }).click();
  await page.getByRole('spinbutton', { name: 'Product quantity' }).fill(quantity);
  await page.getByRole('button', { name: 'Add to cart' }).click();
  await page.waitForTimeout(1000);
  await page.locator('.woocommerce-message').getByRole('link', { name: 'View cart' }).click();

  // proceed to checkout
  await page.waitForLoadState();
  await page.getByRole('link', { name: 'Proceed to Checkout' }).click();
}
