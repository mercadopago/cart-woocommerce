import { expect } from "@playwright/test";
import { goToCustomCheckout, placeOrder } from "./chocustom";

/**
 * E2E coverage for the createCardToken field-error observability.
 *
 * When createCardToken (Custom checkout, Classic and Blocks) rejects a client-side
 * field validation, mp-sdk-metrics.js classifies the failure and emits an
 * `mp_api_error` metric via sendMetric() — a `navigator.sendBeacon` POST to
 *   https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/big/mp_api_error
 * with { message, details:{ api_route:'createCardToken', reason } }:
 *   - invalid_security_fields  — PCI iframe fields (cardNumber/expirationDate/securityCode);
 *                                reason lists which fields.
 *   - invalid_cardholder_fields — non-PCI validateParams (cardholderName/document);
 *                                reason carries the SDK message(s).
 *   - a REAL API/network failure is NOT classified: it stays a real error, so the
 *     metric has no `reason` and message is neither of the two categories above.
 *
 * The pre-submit gates (runPreSubmitGates) check the card number, installments and
 * document — NOT the CVV/expiration or the cardholder name — so we can pass the
 * gates and let createCardToken be the one to reject, which is exactly what the
 * classification wraps.
 */

const MP_API_ERROR_METRIC = '/monitor/v1/event/datadog/big/mp_api_error';
const CARD_TOKENS_ENDPOINT = '**/v1/card_tokens**';

const MESSAGE_SECURITY_FIELDS = 'invalid_security_fields';
const MESSAGE_CARDHOLDER_FIELDS = 'invalid_cardholder_fields';

// MP iframe secure fields.
const cardNumberFrame = (page) => page.frameLocator('iframe[name="cardNumber"]').locator('[name="cardNumber"]');
const securityCodeFrame = (page) => page.frameLocator('iframe[name="securityCode"]').locator('[name="securityCode"]');
const expirationFrame = (page) => page.frameLocator('iframe[name="expirationDate"]').locator('[name="expirationDate"]');

/**
 * Intercepts the metric beacons, capturing every mp_api_error payload into the
 * returned (live) array and stubbing the response so the sendBeacon never leaves
 * the browser (no real call to api.mercadopago.com).
 *
 * MUST be called BEFORE the action that triggers the metric: the createCardToken
 * field validation rejects synchronously, so the beacon fires the instant the
 * order is placed — a page.waitForRequest() started AFTER placeOrder would miss
 * it. Capturing into an array in the route handler decouples from that timing.
 */
export function captureApiErrorBeacons(page) {
  const metrics = [];
  page.route('**/monitor/v1/event/datadog/big/**', (route) => {
    const req = route.request();
    try {
      if (req.url().includes(MP_API_ERROR_METRIC)) {
        metrics.push(JSON.parse(req.postData() || '{}'));
      }
    } catch {
      // ignore non-JSON beacons
    }
    return route.fulfill({ status: 200, contentType: 'application/json', body: '{}' });
  });
  return metrics;
}

/**
 * Polls the captured beacons until one for the given api_route arrives and returns
 * its payload { message, details:{ api_route, reason, ... } }. The SDK may emit the
 * metric more than once (submit retries) — the latest matching payload is returned.
 */
export async function waitForApiErrorMetric(metrics, apiRoute = 'createCardToken', timeout = 40000) {
  await expect
    .poll(() => metrics.some((m) => m?.details?.api_route === apiRoute), { timeout })
    .toBeTruthy();
  return metrics.filter((m) => m?.details?.api_route === apiRoute).pop();
}

/**
 * Fills the MP Custom card form with fully-valid values, then optionally breaks a
 * single field so createCardToken rejects it. Installments are selected
 * unconditionally (unlike chocustom's helper, which skips them when the name is
 * empty) so the installments gate always passes and createCardToken is reached.
 *
 * @param {object} opts
 * @param {object} opts.card  { number, code, date }
 * @param {object} opts.form  { name, docType, docNumber }
 * @param {'cvv'|'name'|null} opts.breakField field to invalidate (empty it)
 */
async function fillControlledCardForm(page, { card, form, breakField = null }) {
  await cardNumberFrame(page).waitFor({ state: 'visible', timeout: 30000 });

  const digits = String(card.number).replace(/\D/g, '');
  for (let attempt = 0; attempt < 3; attempt++) {
    await cardNumberFrame(page).click({ timeout: 15000 });
    await cardNumberFrame(page).fill('');
    await cardNumberFrame(page).pressSequentially(digits, { delay: 50 });
    const entered = (await cardNumberFrame(page).inputValue().catch(() => '')).replace(/\D/g, '');
    if (entered.length === digits.length) break;
  }

  // CVV — empty when breaking the security fields.
  const cvv = breakField === 'cvv' ? '' : card.code;
  await securityCodeFrame(page).click();
  if (cvv) await securityCodeFrame(page).pressSequentially(cvv, { delay: 30 });

  await expirationFrame(page).click();
  await expirationFrame(page).pressSequentially(String(card.date).replace('/', ''), { delay: 30 });

  await page.waitForTimeout(3000);

  // Cardholder name — empty when breaking the cardholder fields.
  const cardholderName = page.locator('#form-checkout__cardholderName');
  const rendered = await cardholderName.waitFor({ state: 'visible', timeout: 12000 }).then(() => true).catch(() => false);
  if (rendered) {
    await cardholderName.fill(breakField === 'name' ? '' : form.name);

    const identificationType = page.locator('#form-checkout__identificationType');
    if (await identificationType.isVisible().catch(() => false)) {
      const docTypeAvailable = await identificationType
        .locator(`option[value="${form.docType}"]`)
        .count()
        .then((n) => n > 0)
        .catch(() => false);
      if (docTypeAvailable) await identificationType.selectOption(form.docType);
      await page.waitForTimeout(2000);
      if (form.docNumber != null) {
        await page.locator('[name="identificationNumber"]').fill(form.docNumber);
      }
    }

    // Select installments unconditionally (not gated on the name) so the
    // installments gate passes and createCardToken is reached even when the
    // name is intentionally empty.
    const installments = page.locator('#mp-checkout-custom-installments-card');
    if (await installments.isVisible().catch(() => false)) {
      await page.locator('#form-checkout__installments').selectOption({ index: 1 });
    }
    await page.waitForLoadState();
  }
}

/**
 * invalid_security_fields — a valid card with an EMPTY CVV passes the gates; the
 * SDK createCardToken rejects the secure field, classified as
 * invalid_security_fields with reason listing securityCode.
 */
export async function assertInvalidSecurityFieldsMetric(page, url, user, card, form) {
  const metrics = captureApiErrorBeacons(page);
  await goToCustomCheckout(page, url, user);
  await fillControlledCardForm(page, { card, form, breakField: 'cvv' });
  await placeOrder(page);

  const metric = await waitForApiErrorMetric(metrics);
  expect(metric.message).toBe(MESSAGE_SECURITY_FIELDS);
  expect(metric.details.api_route).toBe('createCardToken');
  // reason lists the failing secure field(s); securityCode must be among them.
  expect(metric.details.reason).toContain('securityCode');
}

/**
 * invalid_cardholder_fields — a valid card with an EMPTY cardholder name passes
 * the gates; the SDK createCardToken rejects the non-PCI validateParams,
 * classified as invalid_cardholder_fields with a non-empty reason.
 */
export async function assertInvalidCardholderFieldsMetric(page, url, user, card, form) {
  const metrics = captureApiErrorBeacons(page);
  await goToCustomCheckout(page, url, user);
  await fillControlledCardForm(page, { card, form, breakField: 'name' });
  await placeOrder(page);

  const metric = await waitForApiErrorMetric(metrics);
  expect(metric.message).toBe(MESSAGE_CARDHOLDER_FIELDS);
  expect(metric.details.api_route).toBe('createCardToken');
  expect(metric.details.reason, 'cardholder reason must not be dropped').toBeTruthy();
}

/**
 * Real error is NOT categorized — a fully valid card whose card_tokens request is
 * force-failed at the network layer rejects createCardToken with a real (non-array)
 * error. The metric must still fire, but must NOT be classified as one of the field
 * categories and must carry no `reason`.
 */
export async function assertRealErrorNotCategorized(page, url, user, card, form) {
  const metrics = captureApiErrorBeacons(page);
  await page.route(CARD_TOKENS_ENDPOINT, (route) => route.abort());

  await goToCustomCheckout(page, url, user);
  await fillControlledCardForm(page, { card, form, breakField: null });
  await placeOrder(page);

  const metric = await waitForApiErrorMetric(metrics);
  expect(metric.details.api_route).toBe('createCardToken');
  expect(metric.message).not.toBe(MESSAGE_SECURITY_FIELDS);
  expect(metric.message).not.toBe(MESSAGE_CARDHOLDER_FIELDS);
  // A real failure is not a field classification, so no reason is attached.
  expect(metric.details.reason).toBeUndefined();
}
