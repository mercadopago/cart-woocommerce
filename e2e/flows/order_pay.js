import { expect } from "@playwright/test";
import { fillCustomCardForm } from "./chocustom";
import { makeLuhnInvalid } from "./luhn_validation";
import { wpEval } from "../helpers/wp-env";

/**
 * E2E coverage for the Order Pay page (form#order_review), a flow separate from
 * the regular checkout: the buyer pays an EXISTING pending order. The MP Custom
 * card form is the same (SDK iframes + #form-checkout__* fields), but the submit
 * goes through handle3dsPayOrderFormSubmission() (jQuery.post('#')) instead of the
 * WooCommerce native checkout submit.
 *
 * Covered:
 *   - a valid card pays the order;
 *   - a Luhn-invalid card is blocked with the card-specific error on Order Pay too,
 *     the buyer stays on the order-pay page, checkout stays usable.
 *
 * Assertions differ by case:
 *   - APPROVED payment: assert the buyer lands on order-received. With binary_mode
 *     off the MP sandbox returns the order-received page while the order stays
 *     `pending` until the confirmation webhook (unreachable in the test env), so the
 *     order STATUS is not a reliable success signal here — the landing is.
 *   - Luhn-blocked card: the gate blocks the submit client-side, so we assert
 *     SERVER-SIDE that the order stays `pending` (plus the card-specific error).
 */

// A publishable product exists in the docker store (Storefront sample). 14 = "Fone Bluetooth".
// Must be numeric — it is interpolated into the PHP run by wpEval, so a non-numeric
// env value could alter the PHP fragment; fall back to the default if it is not digits.
const ORDER_PAY_PRODUCT_ID = /^\d+$/.test(process.env.ORDER_PAY_PRODUCT_ID || '')
  ? process.env.ORDER_PAY_PRODUCT_ID
  : '14';

const CARD_NUMBER_CONTAINER = '#form-checkout__cardNumber-container';
const CARD_NUMBER_HELPER = '#mp-card-number-helper';

/**
 * Creates a pending order paid via the MP Custom gateway and returns its id and the
 * order-pay URL. Billing comes from the guest user so the order carries a payer
 * email/name; the document is entered in the card form at pay time.
 *
 * The pay URL is built from SHOP_URL's origin (not WP home_url) so it always points
 * at the origin the browser actually reaches in Docker.
 */
export function createPendingCardOrder(shopUrl, user) {
  // Strip quotes/backslashes from every interpolated value — they go straight into
  // the PHP string passed to wpEval, so a stray quote in a fixture would otherwise
  // break the eval with an obscure error.
  const esc = (v) => String(v ?? '').replace(/["'\\]/g, '');
  const a = user.address;
  const out = wpEval(
    `$order = wc_create_order();` +
    `$order->add_product(wc_get_product(${ORDER_PAY_PRODUCT_ID}), 1);` +
    `$order->set_address(array(` +
    `"first_name"=>"${esc(user.firstName)}","last_name"=>"${esc(user.lastName)}","email"=>"${esc(user.email)}",` +
    `"phone"=>"11999999999","address_1"=>"${esc(a.street)}","city"=>"${esc(a.city)}",` +
    `"state"=>"${esc(a.state)}","postcode"=>"${esc(a.zip)}","country"=>"${esc(a.countryId)}"` +
    `), "billing");` +
    `$order->set_payment_method("woo-mercado-pago-custom");` +
    `$order->calculate_totals();` +
    `$order->update_status("pending");` +
    `$order->save();` +
    `echo $order->get_id() . "|" . $order->get_order_key();`
  );

  const [id, key] = String(out).trim().split('|');
  if (!id || !key) {
    throw new Error(`[E2E] createPendingCardOrder: unexpected wp-cli output: "${out}"`);
  }
  const origin = new URL(shopUrl).origin;
  const payUrl = `${origin}/checkout/order-pay/${id}/?pay_for_order=true&key=${key}`;
  return { id, payUrl };
}

/** Returns the current WooCommerce status of an order (e.g. 'pending', 'processing'). */
export function getOrderStatus(orderId) {
  return String(wpEval(`$o = wc_get_order(${orderId}); echo $o ? $o->get_status() : "not_found";`) || '').trim();
}

/**
 * Selects the MP Custom method on the order-pay page when it is not already the
 * active one, then waits for the SDK card iframe to mount.
 */
async function selectCustomOnOrderPay(page) {
  const label = page.locator('label[for="payment_method_woo-mercado-pago-custom"]');
  const radio = page.locator('#payment_method_woo-mercado-pago-custom');
  if (await radio.isVisible({ timeout: 5000 }).catch(() => false)) {
    if (!(await radio.isChecked().catch(() => false))) {
      await label.click().catch(() => radio.check());
    }
  }
  await page.waitForLoadState();
  await page.locator('iframe[name="cardNumber"]').waitFor({ state: 'visible', timeout: 30000 });
}

async function placeOrderReview(page) {
  await expect(page.locator('#place_order')).toBeEnabled({ timeout: 15000 });
  await page.locator('#place_order').click();
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
}

/**
 * Valid card on Order Pay: the payment completes and the buyer lands on the
 * order-received (thank-you) page.
 *
 * We assert the order-received landing rather than the server-side order status:
 * with binary_mode off, the MP sandbox returns the order-received page while the
 * order stays `pending` until the payment-confirmation webhook — which points at an
 * unreachable domain in the test env and never arrives. Reaching order-received is
 * the same success signal the regular-checkout card tests use.
 */
export async function payOnOrderPayApproved(page, shopUrl, user, card, form) {
  const { id, payUrl } = createPendingCardOrder(shopUrl, user);
  expect(getOrderStatus(id)).toBe('pending');

  await page.goto(payUrl, { waitUntil: 'domcontentloaded' });
  await selectCustomOnOrderPay(page);
  await fillCustomCardForm(page, card, form);
  await placeOrderReview(page);

  await page.waitForURL(/order-received/, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await expect(page.locator('.woocommerce-thankyou-order-received')).toBeVisible({ timeout: 30000 });
}

/**
 * Luhn-invalid card on Order Pay: blocked with the card-specific error, the buyer
 * stays on the order-pay page (order still pending), and the button recovers.
 */
export async function luhnBlockedOnOrderPay(page, shopUrl, user, card, form) {
  const invalidCard = { ...card, number: makeLuhnInvalid(card.number) };
  const { id, payUrl } = createPendingCardOrder(shopUrl, user);

  await page.goto(payUrl, { waitUntil: 'domcontentloaded' });
  await selectCustomOnOrderPay(page);
  await fillCustomCardForm(page, invalidCard, form);
  await placeOrderReview(page);

  await expect(page.locator(CARD_NUMBER_CONTAINER)).toHaveClass(/mp-error/, { timeout: 15000 });
  await expect(page.locator(CARD_NUMBER_HELPER)).toBeVisible({ timeout: 15000 });

  // The gate blocked the submit client-side: order untouched, checkout usable.
  expect(getOrderStatus(id)).toBe('pending');
  await expect(page).toHaveURL(/order-pay/);
  await expect(page.locator('#place_order')).toBeEnabled({ timeout: 15000 });
}
