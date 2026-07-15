import { test, expect } from "@playwright/test";
import { mlb } from "../../data/meli_sites";
import { goToCustomCheckout, fillCustomCardForm } from "../../flows/chocustom";
import {
  fillDocument,
  expectDocumentAccepted,
  expectDocumentTypeCNPJ,
  expectDocumentRejected,
  captureCheckoutDocPayload,
} from "../../flows/cnpj_document";

/**
 * PSW-4107 (épico PSW-3869) — Alphanumeric CNPJ on the WooCommerce MLB custom
 * (card) checkout.
 *
 * The feature lives entirely in the front-end:
 *   - packages/narciso/.../InputDocument.js — isValidCNPJ accepts alphanumeric,
 *     setMaskInputDocument is case-insensitive and feeds the hidden field the raw
 *     uppercase value.
 *   - assets/js/checkouts/custom/entities/event-handler.js — setPayerIdentificationInfo
 *     normalizes #payerDocNumber to the raw, uppercase, mask-free value before submit.
 *
 * The number the plugin sends to Mercado Pago is the raw, 14-char, uppercase, mask-free
 * value. It travels in the WooCommerce checkout submit (`?wc-ajax=checkout`) as
 * `mercadopago_custom[doc_number]` / `[doc_type]`, which the PHP gateway maps to MP's
 * `identification.number` / `identification.type`.
 *
 * Central assertion (Mudança 6): we intercept that outgoing request and assert the
 * normalized payload. It is captured BEFORE the API response, so the final payment
 * verdict (Payments may still reject while the activation flag is off) does NOT affect
 * the test — the contract under test is what the PLUGIN sends, not what MP approves.
 *
 * Test data note: 12.ABC.345/01DE-35 and 11.222.333/0001-81 are synthetic CNPJs and
 * 111.444.777-35 is a synthetic CPF — all for testing only (valid check digits), not
 * real documents.
 */

const { shop_url, credit_card_scenarios, guestUserMLB } = mlb;
const { APPROVED } = credit_card_scenarios;

// Alphanumeric CNPJ with valid check digits — masked input vs. expected raw payload.
const CNPJ_ALPHA_MASKED = "12.ABC.345/01DE-35";
const CNPJ_ALPHA_RAW = "12ABC34501DE35";

// Legacy numeric CNPJ (regression) — masked input vs. expected raw payload.
const CNPJ_NUMERIC_MASKED = "11.222.333/0001-81";
const CNPJ_NUMERIC_RAW = "11222333000181";

// Alphanumeric CNPJ with a WRONG check digit (…-99 instead of …-35) — must be rejected.
const CNPJ_ALPHA_INVALID = "12.ABC.345/01DE-99";

// Legacy CPF (regression for the all-types normalization, refactor cc4d995d) — masked
// input vs. expected raw payload. CPF normalization must not regress now that
// normalizeDocumentNumber applies to every document type, not only CNPJ.
const CPF_MASKED = "111.444.777-35";
const CPF_RAW = "11144477735";

// The card scenario only needs to make the WC form valid enough to reach place-order;
// the document is overridden per test by fillDocument().
const card = APPROVED.master;
const cardForm = APPROVED.form;

// Payload capture intercepts the classic checkout submit (?wc-ajax=checkout). In Blocks
// the doc fields ride in meta.paymentMethodData and WooCommerce submits through the Store
// API, so the payload tests are classic-only — they would time out waiting for a request
// that never happens in Blocks. Blocks payload coverage is tracked separately.
const isBlocksCheckout = process.env.CHECKOUT === "blocks";

// AC-1 + AC-2 — the field accepts the alphanumeric CNPJ (no letter rejection) and the
// detected/selected type is CNPJ, not CPF.
test("AC-1/AC-2: Given a guest user on the MLB card checkout, When an alphanumeric CNPJ is entered, Should accept it and type it as CNPJ", async ({ page }) => {
  await goToCustomCheckout(page, shop_url, guestUserMLB);
  await fillCustomCardForm(page, card, cardForm);

  await fillDocument(page, "CNPJ", CNPJ_ALPHA_MASKED);

  await expectDocumentTypeCNPJ(page);
  await expectDocumentAccepted(page, CNPJ_ALPHA_RAW);
});

// AC-3 — the OUTGOING payload (the central assertion): identification.type === 'CNPJ'
// and identification.number === '12ABC34501DE35' (raw, 14 chars, uppercase, no mask).
test("AC-3: Given a guest user submitting an alphanumeric CNPJ on the MLB card checkout, When the outgoing payload is captured, Should carry CNPJ type and the raw uppercase 14-char number", async ({ page }) => {
  test.skip(isBlocksCheckout, "payload capture is classic-only (Blocks submits via the Store API)");
  const payload = await captureCheckoutDocPayload(
    page,
    shop_url,
    guestUserMLB,
    card,
    cardForm,
    "CNPJ",
    CNPJ_ALPHA_MASKED
  );

  // identification.type === 'CNPJ'
  expect(payload.docType).toBe("CNPJ");

  // identification.number === '12ABC34501DE35' — raw, uppercase, mask-free, 14 chars.
  expect(payload.docNumber).toBe(CNPJ_ALPHA_RAW);
  expect(payload.docNumber).toHaveLength(14);
  expect(payload.docNumber).toMatch(/^[A-Z0-9]{12}[0-9]{2}$/);
  expect(payload.docNumber).toBe(payload.docNumber.toUpperCase());

  // NOTE: we intentionally do NOT assert on order-received / payment approval. With the
  // alphanumeric-CNPJ activation flag still off, Payments rejecting this payment is an
  // EXPECTED outcome and must not fail this test. The plugin's responsibility — the
  // normalized payload it sends — is fully asserted above, captured before the API
  // response.
});

// AC-4 — regression: the legacy numeric CNPJ still works and produces the raw 14-digit
// payload with the masking stripped.
test("AC-4: Given a guest user submitting a legacy numeric CNPJ on the MLB card checkout, When the outgoing payload is captured, Should send the raw 14-digit number (regression)", async ({ page }) => {
  test.skip(isBlocksCheckout, "payload capture is classic-only (Blocks submits via the Store API)");
  const payload = await captureCheckoutDocPayload(
    page,
    shop_url,
    guestUserMLB,
    card,
    cardForm,
    "CNPJ",
    CNPJ_NUMERIC_MASKED
  );

  expect(payload.docType).toBe("CNPJ");
  expect(payload.docNumber).toBe(CNPJ_NUMERIC_RAW);
  expect(payload.docNumber).toHaveLength(14);
  expect(payload.docNumber).toMatch(/^\d{14}$/);
});

// AC-5 — a wrong check digit is rejected with a visible error in the field, and the
// buyer is not advanced (no payload is asserted because place-order is blocked by the
// document validation).
test("AC-5: Given a guest user on the MLB card checkout, When an alphanumeric CNPJ with a wrong check digit is entered, Should show a visible field error", async ({ page }) => {
  await goToCustomCheckout(page, shop_url, guestUserMLB);
  await fillCustomCardForm(page, card, cardForm);

  await fillDocument(page, "CNPJ", CNPJ_ALPHA_INVALID);

  await expectDocumentRejected(page);
});

// AC-6 — regression for the broadened normalization (refactor cc4d995d): normalizeDocumentNumber
// now applies to ALL document types, not only CNPJ. CPF must still work — typed as CPF and sent
// as the raw 11-digit number (mask stripped). Mirrors the CPF unit-test case at the E2E level.
// (RUT is intentionally NOT covered here: it is a Chilean document offered on MLC, not on the
// MLB checkout under test.)
test("AC-6: Given a guest user submitting a CPF on the MLB card checkout, When the outgoing payload is captured under the all-types normalization, Should send the raw 11-digit number (regression)", async ({ page }) => {
  test.skip(isBlocksCheckout, "payload capture is classic-only (Blocks submits via the Store API)");
  const payload = await captureCheckoutDocPayload(
    page,
    shop_url,
    guestUserMLB,
    card,
    cardForm,
    "CPF",
    CPF_MASKED
  );

  expect(payload.docType).toBe("CPF");
  expect(payload.docNumber).toBe(CPF_RAW);
  expect(payload.docNumber).toHaveLength(11);
  expect(payload.docNumber).toMatch(/^\d{11}$/);
});
