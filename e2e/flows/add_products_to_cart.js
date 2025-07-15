export const addProductsToCart = async function(page) {
  // choose first item
  await page.waitForLoadState();
  await page.locator('#main .ajax_add_to_cart').first().click();
  
  // add to cart
  await page.waitForLoadState();
  await page.locator('#main .added_to_cart').click();
  
  // proceed to checkout
  await page.waitForLoadState();
  await page.locator('.wp-block-woocommerce-proceed-to-checkout-block a').click();
}
