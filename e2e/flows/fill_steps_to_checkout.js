import { addProductsToCart } from "./add_products_to_cart";
import fillBillingData from "./fill_billing_data";

export const fillStepsToCheckout = async function (page, url, user) {
  await page.goto(url);
  await addProductsToCart(page);
  await fillBillingData(page, user);
}