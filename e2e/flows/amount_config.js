import { expect } from "@playwright/test";
import { goToCustomCheckout, fillCustomCardForm, placeOrder } from "./chocustom";

const { wpEval, setGatewaySetting } = require("../helpers/wp-env");

// Only these keys are mutated by the amount-config scenarios. Snapshotting just
// them (instead of the whole settings blob) keeps restore() free of the
// single-quote escaping hazards of writing arbitrary JSON back through wp-cli.
const MANAGED_GATEWAY_KEYS = [
  "gateway_discount_checkbox",
  "gateway_discount",
  "commission_checkbox",
  "commission",
  "currency_conversion",
];

// WooCommerce stores cart fees with cents; compare money to 2 decimals.
const MONEY_PRECISION = 2;

// ---------------------------------------------------------------------------
// Gateway settings: snapshot / restore + config setters
// ---------------------------------------------------------------------------

export function snapshotGatewaySettings(gatewayId) {
  const settings = JSON.parse(
    wpEval(`echo wp_json_encode(get_option("woocommerce_${gatewayId}_settings", []));`) || "{}"
  );
  const snapshot = {};
  for (const key of MANAGED_GATEWAY_KEYS) {
    snapshot[key] = settings[key] ?? null; // null = key was absent in the pristine state
  }
  return snapshot;
}

export function restoreGatewaySettings(gatewayId, snapshot) {
  for (const [key, value] of Object.entries(snapshot)) {
    if (value === null) {
      wpEval(
        `$s = get_option("woocommerce_${gatewayId}_settings", []); unset($s["${key}"]); update_option("woocommerce_${gatewayId}_settings", $s);`
      );
    } else {
      setGatewaySetting(gatewayId, key, value);
    }
  }
}

// Discount/commission are mp_actionable_input fields: a `{key}_checkbox` gate plus
// the numeric `{key}` value. getActionableValue() returns 0 unless the checkbox is "yes".
export function setDiscount(gatewayId, percent) {
  setGatewaySetting(gatewayId, "gateway_discount_checkbox", "yes");
  setGatewaySetting(gatewayId, "gateway_discount", String(percent));
}

export function disableDiscount(gatewayId) {
  setGatewaySetting(gatewayId, "gateway_discount_checkbox", "no");
}

export function setCommission(gatewayId, percent) {
  setGatewaySetting(gatewayId, "commission_checkbox", "yes");
  setGatewaySetting(gatewayId, "commission", String(percent));
}

export function disableCommission(gatewayId) {
  setGatewaySetting(gatewayId, "commission_checkbox", "no");
}

export function setDiscountAmount(gatewayId, percent) {
  // Sets the value WITHOUT touching the checkbox — used by the regression guard to
  // prove that a configured amount is ignored while the checkbox is off (RN-1 exception).
  setGatewaySetting(gatewayId, "gateway_discount", String(percent));
}

export function setCurrencyConversion(gatewayId, enabled) {
  setGatewaySetting(gatewayId, "currency_conversion", enabled ? "yes" : "no");
}

// ---------------------------------------------------------------------------
// WooCommerce coupons
// ---------------------------------------------------------------------------

export function createCoupon(code, discountType, amount) {
  return wpEval(
    `$c = new WC_Coupon(); $c->set_code("${code}"); $c->set_discount_type("${discountType}"); ` +
    `$c->set_amount(${amount}); $c->save(); echo $c->get_id();`
  );
}

export function deleteCoupon(code) {
  wpEval(`$id = wc_get_coupon_id_by_code("${code}"); if ($id) { wp_delete_post($id, true); } echo "ok";`);
}

export async function applyCouponAtCheckout(page, code) {
  // Classic: a "Have a coupon?" link reveals #coupon_code + the apply button.
  const classicReveal = page.locator("a.showcoupon").first();
  if (await classicReveal.isVisible({ timeout: 3000 }).catch(() => false)) {
    await classicReveal.click();
    const input = page.locator("#coupon_code");
    await input.waitFor({ state: "visible", timeout: 5000 });
    await input.fill(code);
    await page.locator('button[name="apply_coupon"]').click();
    await page.waitForLoadState();
    await page.waitForTimeout(2000); // WC applies the coupon and recalculates via AJAX
    return;
  }

  // Blocks: coupon form is lazy-rendered behind a panel toggle (a <div role="button">, not a <button>).
  const blocksReveal = page
    .locator(
      ".wc-block-components-totals-coupon .wc-block-components-panel__button, " +
        ".wc-block-components-totals-coupon [role='button'], " +
        ".wc-block-components-totals-coupon-link"
    )
    .first();
  if (await blocksReveal.isVisible({ timeout: 3000 }).catch(() => false)) {
    if ((await blocksReveal.getAttribute("aria-expanded").catch(() => null)) !== "true") {
      await blocksReveal.click();
    }
  }
  const blocksInput = page
    .locator("input.wc-block-components-totals-coupon__input, input[id^='wc-block-components-totals-coupon__input']")
    .first();
  await blocksInput.waitFor({ state: "visible", timeout: 5000 });
  await blocksInput.fill(code);
  await page
    .locator(".wc-block-components-totals-coupon__button, .wc-block-components-totals-coupon button[type='submit']")
    .first()
    .click();
  await page.waitForTimeout(2000);
}

// ---------------------------------------------------------------------------
// Payment composition (coupon needs to settle between gateway selection and card entry)
// ---------------------------------------------------------------------------

export async function payWithCustomCardAndCoupon(page, url, user, card, form, couponCode) {
  if (!card?.number) {
    throw new Error(
      `[E2E] Card number is undefined. Check that the env var for this card is set in e2e/.env.\n` +
      `Card object: ${JSON.stringify(card)}`
    );
  }

  await goToCustomCheckout(page, url, user);
  // Apply the coupon BEFORE filling the card: its update_checkout AJAX re-renders the
  // payment box (and the SDK iframes), so the card must be entered against the fresh DOM.
  await applyCouponAtCheckout(page, couponCode);
  await fillCustomCardForm(page, card, form);
  await placeOrder(page);
  await page.waitForURL(/order-received/, { waitUntil: "domcontentloaded", timeout: 60000 });
}

// ---------------------------------------------------------------------------
// Server-side order amount getters (robust against UI/locale variance)
// ---------------------------------------------------------------------------

export function getOrderTotal(orderId) {
  return parseFloat(wpEval(`echo wc_get_order(${orderId})->get_total();`) || "0");
}

export function getOrderSubtotal(orderId) {
  // Sum of line-item subtotals BEFORE coupons/fees — the base the plugin % is applied to.
  return parseFloat(wpEval(`echo wc_get_order(${orderId})->get_subtotal();`) || "0");
}

// The plugin computes discount/commission on Cart::getSubtotal() = cart contents total
// PLUS item tax, evaluated AFTER coupons (the fee hook runs after coupon application in
// calculate_totals). On the order, item->get_total() is the post-coupon net line total and
// get_total_tax() its tax, so their sum reproduces that exact base — keeping the fee
// assertions correct whatever the store's tax rate or active coupon is. (get_subtotal() is
// the wrong base: it is the pre-coupon, tax-EXCLUSIVE line subtotal.)
export function getOrderPluginFeeBase(orderId) {
  return parseFloat(
    wpEval(
      `$o = wc_get_order(${orderId}); $b = 0; foreach ($o->get_items() as $it) { $b += $it->get_total() + $it->get_total_tax(); } echo $b;`
    ) || "0"
  );
}

export function getOrderCouponDiscount(orderId) {
  return parseFloat(wpEval(`echo wc_get_order(${orderId})->get_total_discount();`) || "0");
}

// The plugin adds the discount as a negative fee and the commission as a positive fee.
// Identify them by sign rather than translated name (locale-independent).
export function getOrderDiscountFee(orderId) {
  return Math.abs(
    parseFloat(
      wpEval(
        `$o = wc_get_order(${orderId}); $d = 0; foreach ($o->get_fees() as $f) { if ($f->get_total() < 0) { $d += $f->get_total(); } } echo $d;`
      ) || "0"
    )
  );
}

export function getOrderCommissionFee(orderId) {
  return parseFloat(
    wpEval(
      `$o = wc_get_order(${orderId}); $c = 0; foreach ($o->get_fees() as $f) { if ($f->get_total() > 0) { $c += $f->get_total(); } } echo $c;`
    ) || "0"
  );
}

export function getOrderCurrencyRatio(orderId) {
  return parseFloat(wpEval(`echo wc_get_order(${orderId})->get_meta("_currency_ratio");`) || "0");
}

// ---------------------------------------------------------------------------
// Assertions
// ---------------------------------------------------------------------------

// Validates RN-1/2/3: the discount is a negative fee and the commission a positive fee,
// each equal to `pct%` of the base the plugin actually uses (post-coupon, tax-inclusive
// subtotal). Asserting the fee values — the plugin's own output — is tax-robust; the way
// WooCommerce then folds them (plus store tax) into the order total is core bookkeeping and
// not the plugin's responsibility, so we don't re-derive the gross total here.
export function assertPluginFees(orderId, { discountPct = 0, commissionPct = 0 } = {}) {
  const base = getOrderPluginFeeBase(orderId);

  expect(getOrderDiscountFee(orderId)).toBeCloseTo(base * (discountPct / 100), MONEY_PRECISION);
  expect(getOrderCommissionFee(orderId)).toBeCloseTo(base * (commissionPct / 100), MONEY_PRECISION);
}

// Validates RN-4: a WooCommerce percent coupon reduces the subtotal. get_total_discount()
// is ex-tax, matching the ex-tax get_subtotal() base. No plugin fee is active here.
export function assertCouponApplied(orderId, percent) {
  expect(getOrderCouponDiscount(orderId)).toBeCloseTo(
    getOrderSubtotal(orderId) * (percent / 100),
    MONEY_PRECISION
  );
}

// Documents the coupon × plugin-fee order of application (RN-4): the WooCommerce coupon
// reduces the cart first, and the plugin discount is computed on the POST-coupon base (the
// fee hook runs after coupons in calculate_totals). getOrderPluginFeeBase already reflects
// the post-coupon subtotal, so the gateway discount asserts against the reduced base.
export function assertCouponThenDiscount(orderId, { couponPct, discountPct }) {
  expect(getOrderCouponDiscount(orderId)).toBeCloseTo(
    getOrderSubtotal(orderId) * (couponPct / 100),
    MONEY_PRECISION
  );
  expect(getOrderDiscountFee(orderId)).toBeCloseTo(
    getOrderPluginFeeBase(orderId) * (discountPct / 100),
    MONEY_PRECISION
  );
}

// Validates RN-5 with lower confidence: the live API ratio depends on the market, so we
// assert it was applied (stored, positive, and != 1) rather than a fixed converted value.
export function assertCurrencyConverted(orderId) {
  const ratio = getOrderCurrencyRatio(orderId);
  expect(ratio).toBeGreaterThan(0);
  expect(Math.abs(ratio - 1)).toBeGreaterThan(0.0001);
}

// UI complement (checklist): the plugin fee rows render differently per checkout mode.
// Classic: <tr class="fee"> in the review-order table.
// Blocks: WC renders each fee as an element with className starting with
//   "wc-block-components-totals-fees__" (BEM modifier generated at runtime).
// Detection uses #billing_first_name (Classic-only field), same heuristic as
// fill_steps_to_checkout.js — avoids the circular problem of detecting by the
// very element we're about to assert.
export async function assertFeeLinesVisible(page, expectedCount) {
  const isClassic = await page.locator("#billing_first_name").isVisible({ timeout: 3000 }).catch(() => false);

  if (isClassic) {
    await expect(page.locator("tr.fee")).toHaveCount(expectedCount, { timeout: 10000 });
  } else {
    // Blocks fees appear after the async Store API recalculation triggered by payment
    // method selection — give it a longer window than the Classic table update.
    await expect(
      page.locator("[class*='wc-block-components-totals-fees__']")
    ).toHaveCount(expectedCount, { timeout: 20000 });
  }
}
