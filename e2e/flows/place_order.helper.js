import { expect } from "@playwright/test";

// Clicks WooCommerce's "Place Order" button, handling both Classic and Blocks.
//
// Blocks — wait for the cart to settle before submitting:
// When the gateway is selected, the plugin applies discount/commission via a cart extension
// (`extensionCartUpdate`, see assets/js/blocks/*.block.js). This marks the cart totals dirty
// and triggers background recalculations (POST /wc/store/v1/batch). If the order is submitted
// while one of those is still in flight, WC Blocks runs a totals recalculation
// (POST /wc/store/v1/checkout?__experimental_calc_totals=true) instead of placing the order.
// A real shopper doesn't hit this because there's a natural pause between selecting the method
// and clicking — the recalculation settles first. In automation we reproduce that by waiting
// for the network to go idle, then clicking ONCE (no double-click).
export async function placeOrder(page) {
  const classicPlaceOrder = page.locator('#place_order');
  if (await classicPlaceOrder.isVisible({ timeout: 3000 }).catch(() => false)) {
    await classicPlaceOrder.click();
    return;
  }

  const blocksPlaceOrder = page.locator('.wc-block-components-checkout-place-order-button');
  await expect(blocksPlaceOrder).toBeEnabled({ timeout: 10000 });

  // Let the discount/commission cart update finish before submitting.
  await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});

  await blocksPlaceOrder.click();
}
