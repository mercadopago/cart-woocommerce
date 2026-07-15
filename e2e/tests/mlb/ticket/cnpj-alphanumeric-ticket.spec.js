import { test, expect } from "@playwright/test";
import { mlb } from "../../../data/meli_sites";
import {
  goToTicketCheckout,
  fillTicketDocument,
  expectTicketDocumentAccepted,
  expectTicketDocumentTypeCNPJ,
  expectTicketDocumentRejected,
  captureTicketDocPayload,
} from "../../../flows/cnpj_document_ticket";

/**
 * PSW-4130 / PSW-4107 (epic PSW-3869) — Alphanumeric CNPJ on the WooCommerce MLB
 * TICKET (boleto) checkout.
 *
 * The ticket checkout reuses the same Narciso input-document component as the card
 * checkout (alphanumeric validation/mask). These tests assert:
 *   - Field-level behavior: accepts valid CNPJ, rejects wrong check digit, detects CNPJ type.
 *   - Payload normalization (PSW-4130): the submitted payload carries identification.type=CNPJ
 *     and identification.number as the raw, uppercase, mask-free 14-char value.
 *
 * Payload tests (AC-T3, AC-T6) run in BOTH checkouts. captureTicketDocPayload intercepts
 * the classic `?wc-ajax=checkout` submit (form-encoded) or the Blocks Store API submit
 * (/wc/store/v1/checkout, JSON payment_data) depending on process.env.CHECKOUT.
 *
 * Test data: 12.ABC.345/01DE-35 and 11.222.333/0001-81 are synthetic CNPJs (valid check
 * digits) for testing only. 111.444.777-35 is a synthetic CPF for testing only.
 */

const { shop_url, guestUserMLB } = mlb;

// Alphanumeric CNPJ with valid check digits — masked input vs. expected raw payload.
const ALPHANUMERIC_CNPJ = "12.ABC.345/01DE-35";
const ALPHANUMERIC_CNPJ_RAW = "12ABC34501DE35";

// Legacy numeric CNPJ (regression) — masked input vs. expected raw payload.
const LEGACY_NUMERIC_CNPJ = "11.222.333/0001-81";
const LEGACY_NUMERIC_CNPJ_RAW = "11222333000181";

// Alphanumeric CNPJ with a WRONG check digit — must be rejected.
const ALPHANUMERIC_CNPJ_WRONG_DV = "12.ABC.345/01DE-99";

// CPF (regression for all-types normalization) — masked input vs. expected raw payload.
const CPF_MASKED = "111.444.777-35";
const CPF_RAW = "11144477735";

test("AC-T1/T2: Given a guest user on the MLB ticket checkout, When an alphanumeric CNPJ is entered, Should accept it and type it as CNPJ", async ({ page }) => {
  await goToTicketCheckout(page, shop_url, guestUserMLB);
  await fillTicketDocument(page, "CNPJ", ALPHANUMERIC_CNPJ);
  await expectTicketDocumentTypeCNPJ(page);
  await expectTicketDocumentAccepted(page);
});

test("AC-T4: Given a guest user on the MLB ticket checkout, When a legacy numeric CNPJ is entered, Should still accept it and type it as CNPJ (regression)", async ({ page }) => {
  await goToTicketCheckout(page, shop_url, guestUserMLB);
  await fillTicketDocument(page, "CNPJ", LEGACY_NUMERIC_CNPJ);
  await expectTicketDocumentTypeCNPJ(page);
  await expectTicketDocumentAccepted(page);
});

// AC-T4b — mirrors AC-4 of the card spec: regression for payload normalization on the
// legacy (all-numeric) CNPJ. A bug in the normalization regex would corrupt numeric CNPJs
// too; this test catches that before the change reaches develop.
test("AC-T4b: Given a guest user submitting a legacy numeric CNPJ on the MLB ticket checkout, When the outgoing payload is captured, Should send the raw 14-digit number (regression)", async ({ page }) => {
  const payload = await captureTicketDocPayload(
    page,
    shop_url,
    guestUserMLB,
    "CNPJ",
    LEGACY_NUMERIC_CNPJ
  );

  expect(payload.docType).toBe("CNPJ");
  expect(payload.docNumber).toBe(LEGACY_NUMERIC_CNPJ_RAW);
  expect(payload.docNumber).toHaveLength(14);
  expect(payload.docNumber).toMatch(/^\d{14}$/);
});

test("AC-T5: Given a guest user on the MLB ticket checkout, When an alphanumeric CNPJ with a wrong check digit is entered, Should reject it", async ({ page }) => {
  await goToTicketCheckout(page, shop_url, guestUserMLB);
  await fillTicketDocument(page, "CNPJ", ALPHANUMERIC_CNPJ_WRONG_DV);
  await expectTicketDocumentRejected(page);
});

// AC-T3 — the OUTGOING payload (central assertion for PSW-4130): identification.type === 'CNPJ'
// and identification.number === '12ABC34501DE35' (raw, 14 chars, uppercase, no mask).
// Mirrors AC-3 of the card checkout (cnpj-alphanumeric.spec.js).
test("AC-T3: Given a guest user submitting an alphanumeric CNPJ on the MLB ticket checkout, When the outgoing payload is captured, Should carry CNPJ type and the raw uppercase 14-char number", async ({ page }) => {
  const payload = await captureTicketDocPayload(
    page,
    shop_url,
    guestUserMLB,
    "CNPJ",
    ALPHANUMERIC_CNPJ
  );

  // identification.type === 'CNPJ'
  expect(payload.docType).toBe("CNPJ");

  // identification.number === '12ABC34501DE35' — raw, uppercase, mask-free, 14 chars.
  expect(payload.docNumber).toBe(ALPHANUMERIC_CNPJ_RAW);
  expect(payload.docNumber).toHaveLength(14);
  expect(payload.docNumber).toMatch(/^[A-Z0-9]{12}[0-9]{2}$/);
  expect(payload.docNumber).toBe(payload.docNumber.toUpperCase());

  // NOTE: we intentionally do NOT assert on order-received / payment approval. The
  // plugin's responsibility — the normalized payload it sends — is fully asserted above,
  // captured before the API response.
});

// AC-T6 — regression for the all-types normalization: normalizeDocumentNumber applies
// to ALL document types (the regex strips any mask character, not just CNPJ separators).
// CPF must still work — sent as the raw 11-digit number with mask stripped.
// Mirrors AC-6 of the card checkout (cnpj-alphanumeric.spec.js).
test("AC-T6: Given a guest user submitting a CPF on the MLB ticket checkout, When the outgoing payload is captured under the all-types normalization, Should send the raw 11-digit number (regression)", async ({ page }) => {
  const payload = await captureTicketDocPayload(
    page,
    shop_url,
    guestUserMLB,
    "CPF",
    CPF_MASKED
  );

  expect(payload.docType).toBe("CPF");
  expect(payload.docNumber).toBe(CPF_RAW);
  expect(payload.docNumber).toHaveLength(11);
  expect(payload.docNumber).toMatch(/^\d{11}$/);
});
