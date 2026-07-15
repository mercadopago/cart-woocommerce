import { expect } from "@playwright/test";
import { fillStepsToCheckout } from "./fill_steps_to_checkout";
import { placeOrder } from "./place_order.helper";

/**
 * E2E flow helpers for the alphanumeric-CNPJ feature on the MLB TICKET (boleto)
 * checkout (PSW-4130 / PSW-4107 / epic PSW-3869).
 *
 * Scope: the ticket checkout renders the SAME Narciso <input-document> web component
 * as the card checkout (isValidCNPJ + mask behave identically), AND the ticket
 * checkout now normalizes the submitted payload via:
 *   - Classic: CheckoutTicketPageController.normalizeDocumentNumber() (mp-ticket-checkout.js)
 *   - Blocks:  inline toUpperCase().replace(/[^A-Z0-9]/g,'') on paymentMethodData (ticket.block.js)
 *
 * Helpers here assert both field-level behavior (AC-T1/T2/T4/T5) and the outgoing
 * payload (AC-T3/T6). captureTicketDocPayload covers both checkouts: Classic via the
 * form-encoded `?wc-ajax=checkout` body and Blocks via the JSON `payment_data` array of
 * the Store API `/wc/store/v1/checkout` submit.
 *
 * The ticket input-document is scoped inside `.mp-checkout-ticket-container` and uses
 * ticket-specific ids (select-id `doc_type`, input-id `mp-ticket-gateway-document-input`).
 */

const TICKET_SCOPE = '.mp-checkout-ticket-container';
const TICKET_DOC_TYPE = `${TICKET_SCOPE} select[name="mercadopago_ticket[doc_type]"]`;
// Stable id: an invalid value swaps the input's `name` to flag-error, but the id stays.
const TICKET_DOC_INPUT = `${TICKET_SCOPE} #mp-ticket-gateway-document-input`;
// The error class lands on the input-document container div inside the ticket scope.
const TICKET_DOC_CONTAINER = `${TICKET_SCOPE} input-document .mp-input`;
// The helper visibility is toggled on the #mp-doc-number-helper container div itself
// (InputHelper.validateVisibility), not on the <input-helper> host — target the
// container so we can read its own display, mirroring the card flow.
const TICKET_DOC_HELPER = `${TICKET_SCOPE} input-document #mp-doc-number-helper`;

/**
 * Navigates to checkout, selects the Mercado Pago Ticket (boleto) gateway and ensures
 * the Boleto sub-method is chosen so the document field is interactive.
 * Supports both Classic and Blocks checkout.
 */
export async function goToTicketCheckout(page, url, user) {
  await fillStepsToCheckout(page, url, user);
  await page.waitForLoadState();

  const classicRadio = page.locator('#payment_method_woo-mercado-pago-ticket');
  const blocksRadio = page.locator('#radio-control-wc-payment-method-options-woo-mercado-pago-ticket');

  if (await classicRadio.isVisible({ timeout: 3000 }).catch(() => false)) {
    await page.locator('label[for="payment_method_woo-mercado-pago-ticket"]').click();
    // Deterministic wait: after selecting the gateway, WooCommerce re-renders the
    // ticket fields via updated_checkout. Wait for the document type select to appear
    // instead of a fixed timeout (flaky on slow/congested CI).
    await page.locator(TICKET_DOC_TYPE).waitFor({ state: 'visible', timeout: 10000 });
  } else {
    await blocksRadio.check();
  }
  await page.waitForLoadState();

  // When multiple ticket methods are listed, pick Boleto; with a single method the
  // template renders a hidden input and there is nothing to click.
  const boletoItem = page.getByText('Boleto', { exact: true });
  if (await boletoItem.isVisible({ timeout: 2000 }).catch(() => false)) {
    await boletoItem.click();
    // Deterministic wait for the re-rendered document field to appear.
    await page.locator(TICKET_DOC_TYPE).waitFor({ state: 'visible', timeout: 5000 });
  }
  // SDK init settle: after the gateway/sub-method selection the MP SDK re-populates and
  // resets the document-type select to its default (CPF) asynchronously — and that reset
  // can fire AFTER we have already selected CNPJ and typed the value (it does on the fast
  // ticket flow). The select being visible is not enough, and the SDK exposes no ready
  // signal, so a short fixed settle lets init finish before fillTicketDocument interacts.
  // (The card flow needs no equivalent settle — filling the card form already provides it.)
  await page.waitForTimeout(1000);
}

/**
 * Selects the document type and types the value into the ticket document field,
 * char-by-char so the component's mask + real-time validation listeners fire, then
 * blurs so the container transitions mp-error-2px → mp-error for invalid values.
 */
export async function fillTicketDocument(page, docType, docValue) {
  const docTypeSelect = page.locator(TICKET_DOC_TYPE);
  await docTypeSelect.waitFor({ state: 'visible', timeout: 15000 });

  // The MP SDK populates and then resets this select to its default (CPF) asynchronously
  // after the field renders. Re-select until the value sticks (selectOption throws until
  // the option exists, hence the catch) so the choice survives a late SDK reset without
  // relying on a fixed settle.
  await expect
    .poll(
      async () => {
        await docTypeSelect.selectOption(docType).catch(() => {});
        return docTypeSelect.inputValue();
      },
      { timeout: 15000, message: `ticket doc-type should hold "${docType}" (SDK may reset it to CPF after init)` }
    )
    .toBe(docType);
  await page.waitForTimeout(200);

  const docInput = page.locator(TICKET_DOC_INPUT);
  await docInput.click();
  await docInput.fill('');
  await docInput.pressSequentially(docValue, { delay: 40 });
  await page.evaluate(() => document.activeElement && document.activeElement.blur());
  await page.waitForTimeout(300);
}

/** Asserts the ticket document field accepted the value (no visible validation error). */
export async function expectTicketDocumentAccepted(page) {
  await expect
    .poll(
      () =>
        page.$$eval(TICKET_DOC_CONTAINER, (els) =>
          els.some((el) => /mp-error/.test(el.className))
        ),
      { timeout: 5000, message: 'ticket document container should not carry an mp-error class after a valid input' }
    )
    .toBe(false);
}

/** Asserts the selected ticket document type is CNPJ (not CPF). */
export async function expectTicketDocumentTypeCNPJ(page) {
  const selected = await page.locator(TICKET_DOC_TYPE).inputValue();
  expect(selected).toBe('CNPJ');
}

/** Asserts the ticket document field shows a visible validation error (wrong check digit). */
export async function expectTicketDocumentRejected(page) {
  await expect
    .poll(
      () =>
        page.$$eval(TICKET_DOC_CONTAINER, (els) =>
          els.some((el) => /mp-error/.test(el.className))
        ),
      { timeout: 5000, message: 'ticket document container should carry an mp-error class after an invalid input' }
    )
    .toBe(true);

  // Read the helper container's own display directly (consistent with the card flow's
  // expectDocumentRejected) — visibility is toggled on this container, not on a child.
  const anyHelperVisible = await page.$$eval(TICKET_DOC_HELPER, (els) =>
    els.some((el) => window.getComputedStyle(el).display !== 'none')
  );
  expect(anyHelperVisible).toBe(true);
}

// Classic submit endpoint: doc fields ride as form-encoded params in the request body.
const WC_CLASSIC_CHECKOUT_REGEX = /wc-ajax=checkout/;
// Blocks submit endpoint: WC Store API checkout. The plugin's doc fields ride inside the
// JSON `payment_data` array (the meta.paymentMethodData returned from onPaymentSetup).
const WC_STORE_CHECKOUT_REGEX = /\/wc\/store\/v1\/checkout/;
// Blocks recalculates totals via the same endpoint with this flag before actually placing
// the order — that request carries no payment_data, so it must be excluded.
const WC_STORE_CALC_TOTALS_FLAG = '__experimental_calc_totals';

const isBlocksCheckout = () => process.env.CHECKOUT === 'blocks';

/**
 * Reads `key` from a WC Store API `payment_data` array of `{ key, value }` pairs.
 */
function readFromPaymentData(paymentData, key) {
  const entry = Array.isArray(paymentData) && paymentData.find((item) => item.key === key);
  return entry ? entry.value : '';
}

/**
 * Drives the full MLB ticket checkout, fills the required address fields, intercepts the
 * checkout submit and returns the document payload the plugin sends:
 *   { docNumber, docType } for `mercadopago_ticket[doc_number|doc_type]`.
 *
 * Works for BOTH checkouts; the interception strategy differs:
 *   - Classic: POST `?wc-ajax=checkout` with form-encoded params (URLSearchParams).
 *   - Blocks:  POST `/wc/store/v1/checkout` with a JSON body whose `payment_data` array
 *              carries the fields (the totals-recalculation request is excluded).
 *
 * The WC form must be valid enough to reach place-order (address fields filled with the
 * MLB guest user data). We do NOT assert on payment approval — the boleto gateway may
 * still reject depending on environment configuration. The contract under test is the
 * OUTGOING payload, captured before the API response.
 *
 * @returns {Promise<{docNumber: string, docType: string}>}
 */
export async function captureTicketDocPayload(page, url, user, docType, docValue) {
  await goToTicketCheckout(page, url, user);

  // Fill document type and value after gateway + sub-method selection (goToTicketCheckout
  // handles those) so the SDK-population polling in fillTicketDocument can complete.
  await fillTicketDocument(page, docType, docValue);

  // Fill the required MLB address fields (ticket checkout validates them before submit).
  // The same `#form-checkout__address_*` inputs render in both Classic and Blocks (they
  // come from the gateway's PHP template content).
  await page.locator('#form-checkout__address_zip_code').fill(user.address.zip);
  await page.locator('#form-checkout__address_federal_unit').selectOption(user.address.state);
  await page.locator('#form-checkout__address_city').fill(user.address.city);
  await page.locator('#form-checkout__address_neighborhood').fill(user.address.neighborhood);
  await page.locator('#form-checkout__address_street_name').fill(user.address.street);
  await page.locator('#form-checkout__address_street_number').fill(user.address.number);

  const blocks = isBlocksCheckout();

  // Register the waitForRequest listener BEFORE calling placeOrder so the submit request is
  // never missed: it captures the checkout request (carrying the doc fields) the instant it
  // leaves the browser — before the MP API responds. placeOrder is awaited only afterwards.
  const checkoutRequest = page.waitForRequest(
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
    { timeout: 30000 }
  );

  await placeOrder(page);

  const request = await checkoutRequest;
  const body = request.postData() || '';

  if (blocks) {
    const json = body ? JSON.parse(body) : {};
    return {
      docNumber: readFromPaymentData(json.payment_data, 'mercadopago_ticket[doc_number]'),
      docType: readFromPaymentData(json.payment_data, 'mercadopago_ticket[doc_type]'),
    };
  }

  const params = new URLSearchParams(body);
  return {
    docNumber: params.get('mercadopago_ticket[doc_number]') ?? '',
    docType: params.get('mercadopago_ticket[doc_type]') ?? '',
  };
}
