import { fillBillingData } from "./fill_steps_to_checkout";
import { fillCustomCardForm, placeOrder } from "./chocustom";
import {
  selectMpCustomPaymentMethod,
  assertOrderReceived,
  trackCorsErrors,
} from "./subscriptions";

/**
 * Pays a subscription product on the Blocks checkout with the MP Custom card gateway.
 *
 * The Classic subscription flow needs heavy card-entry patches because WCS's
 * update_order_review AJAX re-renders the payment section and detaches the SDK
 * iframes. The Blocks checkout has no update_order_review cycle — it recalculates
 * the cart through the Store API — so the standard Blocks card path
 * (fillCustomCardForm + placeOrder from chocustom, already proven by the Blocks Luhn
 * test) works without those patches. We only add a settle wait after selecting the
 * gateway so the Store API cart recalculation (MP discount/commission via
 * extensionCartUpdate) finishes before the card fields are typed.
 *
 * Add-to-cart navigates straight to /checkout/ rather than clicking through the
 * add-to-cart notice → cart → "Proceed to checkout" chain, which is Classic-cart
 * specific and flaky; the item is already in the cart session after the product
 * form submits.
 *
 * `email` is passed separately because subscriptions need a unique buyer per run
 * (WCS creates a WP account on the first subscription purchase).
 */
export async function paySubscriptionOnBlocks(page, productUrl, user, email, card, form) {
  const corsErrors = trackCorsErrors(page);
  const origin = new URL(productUrl).origin;

  await page.goto(productUrl, { waitUntil: "domcontentloaded" });
  await page.locator(".single_add_to_cart_button").click();
  await page.waitForLoadState("domcontentloaded");
  await page.goto(`${origin}/checkout/`, { waitUntil: "domcontentloaded" });

  await fillBillingData(page, { ...user, email });
  await selectMpCustomPaymentMethod(page);

  // Let the Blocks Store API cart recalculation settle before typing into the
  // SDK iframes (selecting MP applies discount/commission via extensionCartUpdate).
  await page.waitForTimeout(3000);
  await page.waitForLoadState("networkidle", { timeout: 10000 }).catch(() => {});

  await fillCustomCardForm(page, card, form);
  await placeOrder(page);

  await assertOrderReceived(page, corsErrors);
}
