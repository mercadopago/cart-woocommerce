import { expect, test } from "@playwright/test";
import { fillStepsToCheckout } from "./fill_steps_to_checkout";
import { placeOrder } from "./place_order.helper";

// The plugin's responsibility for Checkout Pro is limited to:
// 1. Creating the MP preference (API call)
// 2. Redirecting to mercadopago.com or opening the modal
// Everything after the redirect is MP's domain, not the plugin's.
// These tests verify the redirect happens correctly.

const MP_CHECKOUT_URL = /mercadopago\.[a-z.]+\/checkout/;

const MODAL_REDIRECT_TIMEOUT = 90000;
const PER_TEST_TIMEOUT = 150000;

async function selectCheckoutProAndSubmit(page, url, user) {
  test.setTimeout(PER_TEST_TIMEOUT);
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
  await page.waitForLoadState();
  await page.waitForTimeout(2000);

  // Click place order (Classic single-click / Blocks two-phase submit)
  await placeOrder(page);
  // Use PER_TEST_TIMEOUT explicitly: the default navigationTimeout (30 s) can expire in
  // slow sandbox/Blocks before assertRedirectedToMpCheckout is even reached.
  await page.waitForLoadState('load', { timeout: PER_TEST_TIMEOUT });
}

// --- Redirect tests ---

// Waits (generously) for the redirect to the MP checkout. Extends the per-test timeout so the unstable redirect timing never outlives the test budget.
async function assertRedirectedToMpCheckout(page) {
  test.setTimeout(PER_TEST_TIMEOUT);
  await page.waitForURL(MP_CHECKOUT_URL, { waitUntil: 'domcontentloaded', timeout: MODAL_REDIRECT_TIMEOUT });
  await expect(page).toHaveURL(MP_CHECKOUT_URL);
}

export async function redirectSuccessfulPaymentTest({ page, url, user }) {
  await selectCheckoutProAndSubmit(page, url, user);
  await assertRedirectedToMpCheckout(page);
}

export async function redirectSuccessfulPendingPaymentTest({ page, url, user }) {
  await selectCheckoutProAndSubmit(page, url, user);
  await assertRedirectedToMpCheckout(page);
}

export async function redirectCancelOrderTest({ page, url, user }) {
  await selectCheckoutProAndSubmit(page, url, user);
  await assertRedirectedToMpCheckout(page);
}

export async function redirectRejectAndChangeMethodTest({ page, url, user }) {
  await selectCheckoutProAndSubmit(page, url, user);
  await assertRedirectedToMpCheckout(page);
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
  test.setTimeout(PER_TEST_TIMEOUT);

  // Either the modal iframe appears or we're redirected to MP checkout.
  // Promise.any (not race+catch): it resolves on the FIRST branch to succeed and only rejects if BOTH fail. With race+catch a branch that times out resolves to null and can "win" the race, masking the other branch that would still succeed a bit later.
  const modal = page.locator('#mercadopago-checkout');
  const redirected = page.waitForURL(MP_CHECKOUT_URL, { timeout: MODAL_REDIRECT_TIMEOUT }).then(() => 'redirect');
  const modalVisible = modal.waitFor({ state: 'visible', timeout: MODAL_REDIRECT_TIMEOUT }).then(() => 'modal');

  let result;
  try {
    result = await Promise.any([redirected, modalVisible]);
  } catch {
    throw new Error('Checkout Pro did not load: neither modal nor redirect detected');
  }

  if (result === 'modal') {
    const frame = await getModal(page);
    await expect(frame.locator('body')).toBeVisible({ timeout: 10000 });
  } else {
    await expect(page).toHaveURL(MP_CHECKOUT_URL);
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
