import { expect } from "@playwright/test";

const { wpEval, wpCli } = require("../helpers/wp-env");

// Path inside the Docker container (the repo is mounted at /woocommerce-mercadopago
// by default; override with PLUGIN_CONTAINER_PATH for non-standard mounts).
const PLUGIN_CONTAINER_PATH = process.env.PLUGIN_CONTAINER_PATH || "/woocommerce-mercadopago";
const REFUND_FIELD_MAP_SCRIPT = `${PLUGIN_CONTAINER_PATH}/e2e/helpers/refund-field-map.php`;

const ADMIN_USER = process.env.WP_ADMIN_USER || "admin";
const ADMIN_PASS = process.env.WP_ADMIN_PASS || "admin";

function originOf(shopUrl) {
  return new URL(shopUrl).origin;
}

// WooCommerce parses price inputs with the store's decimal separator (a comma in pt_BR).
let cachedDecimalSeparator = null;
function priceDecimalSeparator() {
  if (cachedDecimalSeparator === null) {
    cachedDecimalSeparator = wpEval("echo wc_get_price_decimal_separator();") || ".";
  }
  return cachedDecimalSeparator;
}

function formatPrice(value) {
  return Number(value).toFixed(2).replace(".", priceDecimalSeparator());
}

function isHposEnabled() {
  const result = wpEval(
    'echo \\Automattic\\WooCommerce\\Utilities\\OrderUtil::custom_orders_table_usage_is_enabled() ? "1" : "0";'
  );
  return result === "1";
}

export function getOrderEditUrl(shopUrl, orderId) {
  const origin = originOf(shopUrl);
  return isHposEnabled()
    ? `${origin}/wp-admin/admin.php?page=wc-orders&action=edit&id=${orderId}`
    : `${origin}/wp-admin/post.php?post=${orderId}&action=edit`;
}

export function orderIdFromReceivedUrl(page) {
  const url = page.url();
  const pathMatch = url.match(/order-received\/(\d+)/);
  if (pathMatch) {
    return pathMatch[1];
  }

  const params = new URL(url).searchParams;
  const orderId = params.get("order-received") || params.get("order");
  if (!orderId) {
    throw new Error(`[E2E] Could not extract order id from thank-you URL: ${url}`);
  }
  return orderId;
}

export async function loginToAdmin(page, shopUrl) {
  await page.goto(`${originOf(shopUrl)}/wp-admin/`, { waitUntil: "domcontentloaded", timeout: 30000 });

  const loginField = page.locator("#user_login");
  if (await loginField.isVisible({ timeout: 3000 }).catch(() => false)) {
    await loginField.fill(ADMIN_USER);
    await page.fill("#user_pass", ADMIN_PASS);
    await page.click("#wp-submit");
    await page.waitForURL(/\/wp-admin\//, { timeout: 30000 });
  }
}

export function attachRefundDialogHandler(page) {
  const capture = { alertMessage: null };
  page.on("dialog", async dialog => {
    if (dialog.type() === "alert") {
      capture.alertMessage = dialog.message();
    }
    await dialog.accept().catch(() => {});
  });
  return capture;
}

export async function openRefundPanel(page, shopUrl, orderId) {
  await loginToAdmin(page, shopUrl);
  await page.goto(getOrderEditUrl(shopUrl, orderId), { waitUntil: "domcontentloaded", timeout: 30000 });

  const refundButton = page.locator("button.refund-items");

  // On a freshly reset store the first admin render can lag; reload once before giving up.
  for (let attempt = 0; attempt < 2; attempt++) {
    if (await refundButton.isVisible({ timeout: 10000 }).catch(() => false)) {
      await refundButton.click();
      await page.locator(".wc-order-refund-items").waitFor({ state: "visible", timeout: 10000 });
      return true;
    }
    if (attempt === 0) {
      await page.reload({ waitUntil: "domcontentloaded", timeout: 30000 });
    }
  }

  // WooCommerce only renders the Refund button for paid/refundable orders. Surface the
  // actual status so a failure points at the root cause (e.g. order stuck "pending")
  // instead of a bare `expect(false).toBe(true)`.
  const status = getOrderStatus(orderId);
  throw new Error(
    `[E2E] Refund button not found on order ${orderId} edit page (order status: "${status}"). ` +
    "WooCommerce only shows it for paid/refundable orders."
  );
}

/**
 * Simulates the IPN by triggering the plugin's payment-status sync metabox.
 *
 * In local E2E the IPN never arrives (notification_url points to the fake domain
 * e2e-test.example.com set by global-setup), so an approved order stays "pending" and the Refund button never renders. The sync metabox does a real GET on the payment_id (saved synchronously at checkout) and runs the same approvedFlow() the IPN would — moving the order from "pending" to "processing". We exercise the real plugin path instead of forcing payment_complete() directly.
 *
 * Returns the order status after the sync.
 */
export async function syncPaymentStatus(page, shopUrl, orderId) {
  await loginToAdmin(page, shopUrl);
  // waitUntil: "load" (not domcontentloaded): the sync button handler is registered inside window.addEventListener('load', ...) in payment-status-sync.js. Stopping at domcontentloaded leaves a timing window where the handler is not yet attached — waitForEvent("load") would then resolve on the initial load rather than the sync reload, and the subsequent click would be a no-op, leaving the order pending.
  await page.goto(getOrderEditUrl(shopUrl, orderId), { waitUntil: "load", timeout: 30000 });

  const syncButton = page.locator("#mp-sync-payment-status-button");
  if (await syncButton.isVisible({ timeout: 10000 }).catch(() => false)) {
    // The AJAX handler calls location.reload() on success. We must wait for that reload to FINISH before returning — otherwise the caller's next navigation (e.g. openRefundPanel → loginToAdmin) collides with the pending reload.
    // waitForEvent("load") captures the next load event, which is the reload triggered by the sync (not the initial page load, which already completed above).
    const reloadDone = page.waitForEvent("load", { timeout: 30000 }).catch(() => null);
    const synced = page
      .waitForResponse(
        (r) => r.url().includes("admin-ajax") && (r.request().postData() || "").includes("mp_sync_payment_status"),
        { timeout: 30000 }
      )
      .catch(() => null);
    await syncButton.click();
    await synced;
    await reloadDone;
    await page.waitForLoadState("domcontentloaded");
  }
  return getOrderStatus(orderId);
}

export function getRefundFieldMap(orderId) {
  return JSON.parse(wpCli(`eval-file ${REFUND_FIELD_MAP_SCRIPT} ${orderId}`) || "[]");
}

export function getItemLineTotal(orderId) {
  return parseFloat(
    wpEval(`$o = wc_get_order(${orderId}); foreach ($o->get_items() as $it) { echo $it->get_total(); break; }`) || "0"
  );
}

async function fillRefundInput(page, name, value) {
  const input = page.locator(`input[name="${name}"]`);
  await input.fill(formatPrice(value));
  await input.dispatchEvent("change");
}

export async function setLineRefundAmount(page, amount) {
  const input = page.locator("input.refund_line_total").first();
  await input.waitFor({ state: "visible", timeout: 10000 });
  await input.fill(formatPrice(amount));
  await input.dispatchEvent("change");
}

export async function setFullRefundAmounts(page, orderId) {
  const fields = getRefundFieldMap(orderId);
  for (const field of fields) {
    await fillRefundInput(page, field.name, field.value);
  }
}

export async function clickApiRefund(page, dialogCapture) {
  const doApiRefund = page.locator("button.do-api-refund");
  await expect(doApiRefund).toBeVisible({ timeout: 10000 });

  const reloaded = page.waitForEvent("load", { timeout: 30000 }).then(() => true).catch(() => false);
  await doApiRefund.click();

  const outcome = await Promise.race([
    reloaded.then(ok => (ok ? "reloaded" : null)),
    waitForAlert(dialogCapture, 30000).then(msg => (msg !== null ? "alert" : null)),
  ]);

  if (outcome === null) {
    throw new Error("clickApiRefund: neither page reload nor alert dialog occurred within 30 s — unexpected regression");
  }

  return outcome;
}

async function waitForAlert(dialogCapture, timeoutMs) {
  const deadline = Date.now() + timeoutMs;
  while (Date.now() < deadline) {
    if (dialogCapture?.alertMessage) {
      return dialogCapture.alertMessage;
    }
    await new Promise(resolve => setTimeout(resolve, 200));
  }
  return null;
}

export function getOrderTotal(orderId) {
  return parseFloat(wpEval(`echo wc_get_order(${orderId})->get_total();`) || "0");
}

export function getTotalRefunded(orderId) {
  return parseFloat(wpEval(`echo wc_get_order(${orderId})->get_total_refunded();`) || "0");
}

export function getOrderStatus(orderId) {
  return wpEval(`echo wc_get_order(${orderId})->get_status();`) || "";
}

export function markOrderAsSuperToken(orderId) {
  wpEval(
    `$o = wc_get_order(${orderId}); $o->update_meta_data("checkout_type", "super_token"); $o->save(); echo "ok";`
  );
}

export async function assertRefundSuccess(page, orderId, expectedRefunded) {
  await expect(page.locator(".wc-order-totals .refunded-total, tr.refund").first()).toBeVisible({ timeout: 15000 });

  const refunded = getTotalRefunded(orderId);
  expect(Math.abs(refunded - expectedRefunded)).toBeLessThan(0.01);
}

export async function assertTotalRefunded(page, orderId) {
  await expect(page.locator(".wc-order-totals .refunded-total, tr.refund").first()).toBeVisible({ timeout: 15000 });

  // The shopkeeper-facing order note only shows up on a full refund (status → refunded);
  // WooCommerce core wording varies by version/locale, so match the stable "refunded" stem.
  await expect(
    page.locator("#woocommerce-order-notes .note_content, .order_notes .note_content")
      .filter({ hasText: /reembolsado|refunded/i })
      .first()
  ).toBeVisible({ timeout: 10000 });

  expect(getOrderStatus(orderId)).toBe("refunded");
  const orderTotal = getOrderTotal(orderId);
  const refunded = getTotalRefunded(orderId);
  expect(Math.abs(refunded - orderTotal)).toBeLessThan(0.01);
}

export function assertRefundError(dialogCapture, pattern, orderId) {
  expect(dialogCapture.alertMessage).not.toBeNull();
  expect(dialogCapture.alertMessage).toMatch(pattern);

  expect(getTotalRefunded(orderId)).toBe(0);
}
