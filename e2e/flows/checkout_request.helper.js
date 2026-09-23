// Classic checkout submits form-encoded fields through WooCommerce AJAX.
const WC_CLASSIC_CHECKOUT_REGEX = /wc-ajax=checkout/;
// Blocks checkout submits payment data as JSON through the WooCommerce Store API.
const WC_STORE_CHECKOUT_REGEX = /\/wc\/store\/v1\/checkout/;
// Blocks uses the same endpoint to recalculate totals before the real order submit.
const WC_STORE_CALC_TOTALS_FLAG = '__experimental_calc_totals';

const isBlocksCheckout = () => process.env.CHECKOUT === 'blocks';

/**
 * Waits for the actual WooCommerce checkout submit in Classic or Blocks.
 * Store API total-recalculation requests are deliberately ignored because they do
 * not carry the gateway payment_data used to place the order.
 */
export function waitForCheckoutSubmitRequest(page, timeout) {
  const blocks = isBlocksCheckout();

  return page.waitForRequest(
    (request) => {
      if (request.method() !== 'POST') return false;

      if (blocks) {
        return (
          WC_STORE_CHECKOUT_REGEX.test(request.url()) &&
          !request.url().includes(WC_STORE_CALC_TOTALS_FLAG)
        );
      }

      return WC_CLASSIC_CHECKOUT_REGEX.test(request.url());
    },
    { timeout }
  );
}

/**
 * Reads a gateway field from the captured checkout request.
 * Classic uses form-encoded fields; Blocks uses payment_data key/value entries.
 */
export function readCheckoutPaymentData(request, key) {
  const body = request.postData() || '';

  if (!isBlocksCheckout()) {
    return new URLSearchParams(body).get(key) ?? '';
  }

  const json = body ? JSON.parse(body) : {};
  const entry = Array.isArray(json.payment_data)
    ? json.payment_data.find((item) => item.key === key)
    : null;

  return entry ? entry.value : '';
}
