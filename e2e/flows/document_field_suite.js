import { test, expect } from "@playwright/test";
import { goToCustomCheckout, fillCustomCardForm } from "./chocustom";
import {
  fillDocument,
  expectDocumentAccepted,
  expectDocumentRejected,
  expectEmptyDocumentError,
} from "./cnpj_document";
const { skipIfNotSite } = require("../helpers/site-guard");

/**
 * Registers a uniform document-field suite for one site's custom (card) checkout:
 *   - mask conformance + acceptance (per document type),
 *   - required-field error when the field is left empty (per type),
 *   - rejection of a wrong check digit (DV documents only).
 *
 * Every country reuses the same Narciso <input-document> helpers, so the assertions
 * are identical across sites. They run at the field level, captured BEFORE place-order,
 * so they do not depend on the MP sandbox approving a payment.
 *
 * @param {object}   cfg
 * @param {string}   cfg.siteId       e.g. 'MLA' — the run is skipped unless the store matches.
 * @param {object}   cfg.site         the meli_sites entry (shop_url + credit_card_scenarios).
 * @param {object}   cfg.guestUser    the site's guest user fixture.
 * @param {Array}    cfg.maskCases    [{ type, raw, masked, rawHidden, label? }]
 * @param {string[]} cfg.emptyTypes   document types to assert the empty required-field error on.
 * @param {Array}    cfg.invalidCases [{ type, invalid, label? }] — DV documents only.
 */
export function registerDocumentFieldTests({ siteId, site, guestUser, maskCases, emptyTypes = [], invalidCases = [] }) {
  const { shop_url, credit_card_scenarios } = site;
  const { APPROVED } = credit_card_scenarios;
  const card = APPROVED.master;
  // The card only needs a recognized BIN so the SDK renders the identification-type
  // select; the document is set per case by the helpers. Strip the doc fields from the
  // shared form so fillCustomCardForm does not race the SDK populating the select.
  const { docType: _dt, docNumber: _dn, ...cardOnlyForm } = APPROVED.form;

  test.beforeEach(() => {
    skipIfNotSite(test, siteId);
  });

  async function openCardForm(page) {
    await goToCustomCheckout(page, shop_url, guestUser);
    await fillCustomCardForm(page, card, cardOnlyForm);
  }

  for (const { type, raw, masked, rawHidden, label } of maskCases) {
    test(`Given the ${siteId} card checkout, When a ${label || type} "${raw}" is typed, Then it is masked as "${masked}" and accepted`, async ({ page }) => {
      await openCardForm(page);
      await fillDocument(page, type, raw);

      // Visible input shows the grouped mask; the hidden field holds the raw value.
      const shown = await page.locator('[name="identificationNumber"]').inputValue();
      expect(shown).toBe(masked);
      await expectDocumentAccepted(page, rawHidden);
    });
  }

  for (const type of emptyTypes) {
    test(`Given the ${siteId} card checkout, When the ${type} field is left empty, Then it shows the required-field error`, async ({ page }) => {
      await openCardForm(page);
      await expectEmptyDocumentError(page, type);
    });
  }

  for (const { type, invalid, label } of invalidCases) {
    test(`Given the ${siteId} card checkout, When an invalid ${label || type} "${invalid}" is typed, Then it is rejected`, async ({ page }) => {
      await openCardForm(page);
      await fillDocument(page, type, invalid);
      await expectDocumentRejected(page);
    });
  }
}
