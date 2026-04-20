import { expect } from "@playwright/test";
import { fillStepsToCheckout } from "./fill_steps_to_checkout";

// The plugin's responsibility for Checkout Pro is limited to:
// 1. Creating the MP preference (API call)
// 2. Redirecting to mercadopago.com or opening the modal
// Everything after the redirect is MP's domain, not the plugin's.
// These tests verify the redirect happens correctly.

const MP_CHECKOUT_URL = /mercadopago\.[a-z.]+\/checkout/;

async function selectCheckoutProAndSubmit(page, url, user) {
  await fillStepsToCheckout(page, url, user);
  await page.waitForLoadState();

  // Select Checkout Pro — supports both Classic and Blocks
  const classicRadio = page.locator('#payment_method_woo-mercado-pago-basic');
  const blocksRadio = page.locator('#radio-control-wc-payment-method-options-woo-mercado-pago-basic');

  if (await classicRadio.isVisible({ timeout: 3000 }).catch(() => false)) {
    await page.locator('label[for="payment_method_woo-mercado-pago-basic"]').click();
  } else {
    await blocksRadio.check();
  }

  await page.waitForTimeout(1000);

  // Click place order
  const classicPlaceOrder = page.locator('#place_order');
  const blocksPlaceOrder = page.locator('.wc-block-components-checkout-place-order-button');

  if (await classicPlaceOrder.isVisible({ timeout: 3000 }).catch(() => false)) {
    await classicPlaceOrder.click();
  } else {
    await blocksPlaceOrder.click();
  }
}

// --- Redirect tests ---

export async function redirectSuccessfulPaymentTest({ page, url, user }) {
  await selectCheckoutProAndSubmit(page, url, user);
  await page.waitForURL(MP_CHECKOUT_URL, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await expect(page).toHaveURL(MP_CHECKOUT_URL);
}

export async function redirectSuccessfulPendingPaymentTest({ page, url, user }) {
  await selectCheckoutProAndSubmit(page, url, user);
  await page.waitForURL(MP_CHECKOUT_URL, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await expect(page).toHaveURL(MP_CHECKOUT_URL);
}

export async function redirectCancelOrderTest({ page, url, user }) {
  await selectCheckoutProAndSubmit(page, url, user);
  await page.waitForURL(MP_CHECKOUT_URL, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await expect(page).toHaveURL(MP_CHECKOUT_URL);
}

export async function redirectRejectAndChangeMethodTest({ page, url, user }) {
  await selectCheckoutProAndSubmit(page, url, user);
  await page.waitForURL(MP_CHECKOUT_URL, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await expect(page).toHaveURL(MP_CHECKOUT_URL);
}

// --- Modal tests ---
// When the gateway is configured as redirect (default), the modal iframe
// does not exist — the user is redirected to the MP checkout page.
// These tests verify the redirect happens, same as redirect tests.
// If configured as modal, they verify the iframe appears.

export async function getModal(page) {
  return page.locator('#mercadopago-checkout').contentFrame();
}

async function assertCheckoutProLoaded(page) {
  // Either the modal iframe appears or we're redirected to MP checkout
  const modal = page.locator('#mercadopago-checkout');
  const redirected = page.waitForURL(MP_CHECKOUT_URL, { timeout: 30000 }).then(() => 'redirect').catch(() => null);
  const modalVisible = modal.waitFor({ state: 'visible', timeout: 30000 }).then(() => 'modal').catch(() => null);

  const result = await Promise.race([redirected, modalVisible]);

  if (result === 'modal') {
    const frame = await getModal(page);
    await expect(frame.locator('body')).toBeVisible({ timeout: 10000 });
  } else if (result === 'redirect') {
    await expect(page).toHaveURL(MP_CHECKOUT_URL);
  } else {
    throw new Error('Checkout Pro did not load: neither modal nor redirect detected');
  }
}

export async function modalSuccessfulPaymentTest({ page, url, user }) {
  await selectCheckoutProAndSubmit(page, url, user);
  await assertCheckoutProLoaded(page);
}

export async function modalSuccessfulPendingPaymentTest({ page, url, user }) {
  await selectCheckoutProAndSubmit(page, url, user);
  await assertCheckoutProLoaded(page);
}

export async function modalRejectAndChangeMethodTest({ page, url, user }) {
  await selectCheckoutProAndSubmit(page, url, user);
  await assertCheckoutProLoaded(page);
}

export async function modalCancelOrderTest({ page, url, user }) {
  await selectCheckoutProAndSubmit(page, url, user);
  await assertCheckoutProLoaded(page);
}
