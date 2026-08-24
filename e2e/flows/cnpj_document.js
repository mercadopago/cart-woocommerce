import { expect } from "@playwright/test";
import { goToCustomCheckout, fillCustomCardForm, placeOrder } from "./chocustom";

/**
 * E2E flow helpers for the alphanumeric-CNPJ feature (PSW-3869 / PSW-4107).
 *
 * Feature under test (Narciso input-document + custom checkout event handler):
 *   - InputDocument.isValidCNPJ accepts an alphanumeric CNPJ (letters in the first
 *     12 positions, 2 numeric check digits) and rejects a wrong check digit.
 *   - setMaskInputDocument is case-insensitive and feeds the hidden field with the
 *     RAW, UPPERCASE, mask-free value.
 *   - event-handler.setPayerIdentificationInfo normalizes #payerDocNumber to the raw
 *     uppercase value (no mask) before the WooCommerce checkout POST.
 *
 * The value the plugin actually sends to Mercado Pago travels in the WooCommerce
 * checkout submit (classic: `?wc-ajax=checkout`) as the urlencoded fields
 * `mercadopago_custom[doc_number]` / `mercadopago_custom[doc_type]` — the PHP gateway
 * forwards these to MP as `identification.number` / `identification.type`. We assert
 * on THAT request body: it is the payload the plugin controls and it is captured
 * BEFORE the MP API responds, so the test is independent of whether Payments ends up
 * approving or rejecting the payment (the activation flag may still be off).
 */

// The custom-checkout document field is the Narciso <input-document> web component.
const DOC_TYPE_SELECT = '#form-checkout__identificationType';
const DOC_NUMBER_INPUT = '[name="identificationNumber"]';
// The SDK may render a second element with the same id alongside the plugin's
// component — use .first() everywhere this container is resolved.
const DOC_CONTAINER = '#form-checkout__identificationNumber-container';
const DOC_HIDDEN_RAW = '#form-checkout__identificationNumber';
const DOC_HELPER = '#mp-doc-number-helper';

// WooCommerce classic checkout submit endpoint. The plugin's doc fields ride in its body.
const WC_CHECKOUT_ENDPOINT_REGEX = /wc-ajax=checkout/;

/**
 * Fills the Narciso document field with the given type + value, typing the value
 * character-by-character so the component's `input` listener (mask + real-time
 * validation + hidden-field sync) fires exactly as it does for a real buyer.
 *
 * Selecting "CNPJ" is only possible after the MP SDK card form has populated the
 * identification-type select, so this must run AFTER fillCustomCardForm.
 */
export async function fillDocument(page, docType, docValue) {
  const docTypeSelect = page.locator(DOC_TYPE_SELECT);
  await docTypeSelect.waitFor({ state: 'visible', timeout: 15000 });

  // The select is populated by the MP SDK, which may also reset it to its default
  // asynchronously after init. Re-select until the value sticks (selectOption throws
  // until the option exists, hence the catch) so we don't depend on a fixed settle and
  // the choice survives a late SDK reset.
  await expect
    .poll(
      async () => {
        await docTypeSelect.selectOption(docType).catch(() => {});
        return docTypeSelect.inputValue();
      },
      { timeout: 15000, message: `identification-type select should hold "${docType}" (SDK populates/resets it asynchronously)` }
    )
    .toBe(docType);
  await page.waitForTimeout(200);

  const docInput = page.locator(DOC_NUMBER_INPUT);
  await docInput.click();
  await docInput.fill('');
  // pressSequentially so each keystroke triggers the mask + validation listeners.
  await docInput.pressSequentially(docValue, { delay: 40 });
  // Blur the currently focused element so the component runs handleInputFocusOut
  // (mp-error-2px → mp-error). Blur the active element directly: an invalid value
  // swaps the input's `name` to flag-error, so a name-based locator would no longer
  // resolve it.
  await page.evaluate(() => document.activeElement && document.activeElement.blur());
  await page.waitForTimeout(300);
}

/**
 * Asserts the document field accepted the value with no visible validation error:
 *   - the error helper is hidden,
 *   - the container carries no error class,
 *   - the hidden field holds the RAW, UPPERCASE, mask-free value.
 */
export async function expectDocumentAccepted(page, expectedRaw) {
  // The DOM can hold more than one element with this id (the plugin's Narciso
  // component plus an SDK-rendered one). Assert NONE of them carries an error class,
  // instead of guessing which match is the live component.
  await expect
    .poll(
      () =>
        page.$$eval(DOC_CONTAINER, (els) =>
          els.some((el) => /mp-error/.test(el.className))
        ),
      { timeout: 5000, message: 'document container should not carry an mp-error class after a valid input' }
    )
    .toBe(false);

  // The helper visibility is toggled on the #mp-doc-number-helper container itself
  // (InputHelper.validateVisibility sets display none/flex on that div), not on its
  // children. Assert that NO container with this id is visible — there may be more than
  // one element sharing the id (plugin component + SDK-rendered).
  const anyHelperVisible = await page.$$eval(DOC_HELPER, (els) =>
    els.some((el) => window.getComputedStyle(el).display !== 'none')
  );
  expect(anyHelperVisible).toBe(false);

  // The hidden field (hidden-id) is what setMaskInputDocument normalizes: raw, uppercase.
  // Use .first(): the SDK may render a second element with the same id, which would make
  // the locator ambiguous (strict-mode error) — mirror the duplicate-id handling above.
  const hiddenRaw = await page.locator(DOC_HIDDEN_RAW).first().inputValue();
  expect(hiddenRaw).toBe(expectedRaw);
}

/** Asserts the selected identification type is CNPJ (not CPF). */
export async function expectDocumentTypeCNPJ(page) {
  const selected = await page.locator(DOC_TYPE_SELECT).inputValue();
  expect(selected).toBe('CNPJ');
}

/**
 * Asserts the document field shows a visible validation error (wrong check digit).
 */
export async function expectDocumentRejected(page) {
  // At least one of the (possibly duplicated) containers must carry the error class
  // after the field loses focus (mp-error-2px → mp-error in handleNonEmptyInput).
  await expect
    .poll(
      () =>
        page.$$eval(DOC_CONTAINER, (els) =>
          els.some((el) => /mp-error/.test(el.className))
        ),
      { timeout: 5000, message: 'document container should carry an mp-error class after an invalid input' }
    )
    .toBe(true);

  // And the error helper of that same component must be visible. Visibility is toggled
  // on the #mp-doc-number-helper container itself, so read its own display (not a child).
  const anyHelperVisible = await page.$$eval(DOC_HELPER, (els) =>
    els.some((el) => window.getComputedStyle(el).display !== 'none')
  );
  expect(anyHelperVisible).toBe(true);
}

/**
 * Asserts the document field surfaces the required-field error while empty.
 *
 * The empty error is real-time-WHILE-EDITING: setInvalidState shows it as the field
 * becomes empty, and handleInputFocusOut clears it on blur. So we type one digit and
 * delete it (firing the component's `input` listener with an empty value) and assert
 * WITHOUT blurring. Keystrokes go through page.keyboard on the focused input: an
 * invalid/partial value swaps the input's `name` to flag-error mid-edit, so a
 * name-based locator would stop resolving between key presses.
 */
export async function expectEmptyDocumentError(page, docType) {
  const docTypeSelect = page.locator(DOC_TYPE_SELECT);
  await docTypeSelect.waitFor({ state: 'visible', timeout: 15000 });
  await expect
    .poll(
      async () => {
        await docTypeSelect.selectOption(docType).catch(() => {});
        return docTypeSelect.inputValue();
      },
      { timeout: 15000, message: `identification-type select should hold "${docType}"` }
    )
    .toBe(docType);
  await page.waitForTimeout(200);

  const docInput = page.locator(DOC_NUMBER_INPUT);
  await docInput.click();
  await page.keyboard.type('1', { delay: 40 });
  await page.keyboard.press('Backspace');
  await page.waitForTimeout(200);

  // Still focused (no blur): the empty state must carry the error class and show the helper.
  await expect
    .poll(
      () =>
        page.$$eval(DOC_CONTAINER, (els) =>
          els.some((el) => /mp-error/.test(el.className))
        ),
      { timeout: 5000, message: 'document container should carry an mp-error class while empty and focused' }
    )
    .toBe(true);

  const anyHelperVisible = await page.$$eval(DOC_HELPER, (els) =>
    els.some((el) => window.getComputedStyle(el).display !== 'none')
  );
  expect(anyHelperVisible).toBe(true);
}

/**
 * Drives the full custom (card) checkout for MLB with a given CNPJ, intercepts the
 * WooCommerce checkout submit and returns the document payload the plugin sends:
 *   { docNumber, docType } parsed from `mercadopago_custom[doc_number|doc_type]`.
 *
 * The card itself uses an APRO test card so the WC form is valid and place-order
 * proceeds to the submit POST. We do NOT assert on payment approval — Payments may
 * still reject (alphanumeric-CNPJ activation flag off). The contract under test is
 * the OUTGOING payload, captured before the API response.
 *
 * @returns {Promise<{docNumber: string, docType: string}>}
 */
export async function captureCheckoutDocPayload(page, url, user, card, form, docType, docValue) {
  await goToCustomCheckout(page, url, user);

  // Strip document fields from the card form: fillDocument below handles them with
  // proper SDK-population polling. Passing docType here would race against the SDK
  // populating the select, causing selectOption to time out on an empty dropdown.
  const { docType: _dt, docNumber: _dn, ...cardOnlyForm } = form;
  await fillCustomCardForm(page, card, cardOnlyForm);

  // Set document type and value after the card form — fillDocument waits for the
  // SDK to populate the identification-type select before selecting.
  await fillDocument(page, docType, docValue);

  // Capture the WooCommerce checkout submit request (carries the doc fields) the
  // instant it leaves the browser — before the MP API responds.
  const checkoutRequest = page.waitForRequest(
    (request) =>
      request.method() === 'POST' && WC_CHECKOUT_ENDPOINT_REGEX.test(request.url()),
    { timeout: 30000 }
  );

  await placeOrder(page);

  const request = await checkoutRequest;
  const body = request.postData() || '';
  const params = new URLSearchParams(body);

  return {
    docNumber: params.get('mercadopago_custom[doc_number]') ?? '',
    docType: params.get('mercadopago_custom[doc_type]') ?? '',
  };
}
