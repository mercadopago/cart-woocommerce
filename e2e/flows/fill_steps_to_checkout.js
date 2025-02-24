import addProductToCart from "./add_product_to_cart";
import fillBillingData from "./fill_billing_data";

export default async function fillStepsToCheckout(page, url, user) {
  await page.goto(url);
  await addProductToCart(page);
  await fillBillingData(page, user);
}
