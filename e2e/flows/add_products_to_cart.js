export const addProductsToCart = async function(page) {
  // take to shop page
  await page.waitForLoadState();
  await page.getByRole('link', { name: 'Shop' }).first().click();
  
  // choose first item
  await page.waitForLoadState();
  await page.locator('#main .ajax_add_to_cart').first().click();
  
  // add to cart
  await page.waitForTimeout(1000);
  await page.locator('#main .added_to_cart').click();
  
  // proceed to checkout
  await page.waitForLoadState();
  await page.getByRole('link', { name: 'Proceed to Checkout' }).click();
}
