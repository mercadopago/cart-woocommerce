import { test } from "@playwright/test";
import { placeOrder } from "./place_order.helper";
const { e2eTimeout } = require("../helpers/runtime-timeouts");

export default async function(page) {
  test.setTimeout(e2eTimeout(120000));
  await page.waitForLoadState();

  // Select PIX — supports both Classic and Blocks radio IDs
  const classicRadio = page.locator('#payment_method_woo-mercado-pago-pix');
  const blocksRadio = page.locator('#radio-control-wc-payment-method-options-woo-mercado-pago-pix');

  if (await classicRadio.isVisible({ timeout: e2eTimeout(3000) }).catch(() => false)) {
    await page.locator('label[for="payment_method_woo-mercado-pago-pix"]').click();
  } else {
    await blocksRadio.check();
  }

  // Wait for Classic checkout's update_order_review AJAX to complete
  await page.waitForTimeout(2000);

  // Click place order (Classic single-click / Blocks two-phase submit)
  await placeOrder(page);

  await page.waitForURL(/order-received/, { waitUntil: 'domcontentloaded', timeout: e2eTimeout(60000) });
}
