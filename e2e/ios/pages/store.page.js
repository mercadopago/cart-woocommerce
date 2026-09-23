import { BasePage } from './base.page';

export class StorePage extends BasePage {
  constructor(driver, shopUrl, productId) {
    super(driver);
    this.shopUrl = shopUrl;
    this.productId = productId;
  }

  async addProductAndOpenCheckout() {
    await this.driver.get(`${this.shopUrl}/cart/?add-to-cart=${encodeURIComponent(this.productId)}`);

    // Target /cart/ directly so the initial request and WooCommerce's eventual
    // add-to-cart redirect converge on the same URL. Safari/Web Inspector may
    // resolve driver.get before that redirect and must not be raced by /checkout/.
    await this.driver.wait(
      async () => {
        const currentUrl = await this.driver.getCurrentUrl();
        if (!/\/cart\/?(?:$|[?#])/.test(currentUrl)) return false;
        return this.driver.executeScript(
          'return document.readyState === "complete" && Boolean(document.querySelector("form.woocommerce-cart-form .cart_item"))',
        );
      },
      30000,
      'WooCommerce did not render the product in the cart',
      500,
    );
    await this.driver.get(`${this.shopUrl}/checkout/`);

    await this.driver.wait(
      async () => /\/checkout\/?(?:$|[?#])/.test(await this.driver.getCurrentUrl()),
      30000,
      'WooCommerce did not remain on the checkout page; the cart may be empty',
      500,
    );
  }
}
