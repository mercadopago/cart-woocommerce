import { addProductsToCart, addMultipleProductsToCart } from "./add_products_to_cart";
import fillBillingData from "./fill_billing_data";

export const fillStepsToCheckout = async function (page, url, user) {
  await page.goto(url);
  await addProductsToCart(page);
  await fillBillingData(page, user);
}

export const fillStepsToCheckoutMulti = async function (page, url, user, quantity) {
  await page.goto(url);
  await addMultipleProductsToCart(page, quantity);
  await fillBillingData(page, user);
}