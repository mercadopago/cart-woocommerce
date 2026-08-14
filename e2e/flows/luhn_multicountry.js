import { test } from "@playwright/test";
import { luhnBlockedClassic, luhnBlockedBlocks } from "./luhn_validation";
import { skipIfNotSite } from "../helpers/site-guard";

/**
 * Registers the Luhn-block scenario for one country, so the same deterministic check
 * runs across the whole market matrix without duplicating the assertions. Each
 * per-country spec calls this once with its own data/user.
 *
 * The Luhn block is client-side (the gate rejects the checksum before tokenization),
 * so it does not depend on the country's sandbox approving a payment — it exercises
 * the validation the same way in every market. It runs on the country's own
 * APPROVED.master test card (a valid BIN so the SDK still identifies the brand and
 * loads installments) with only the check digit broken.
 *
 * `skipIfNotSite` guards against running a country's spec against the wrong store
 * (the store is selected via SITE per country; this is the safety net).
 *
 * @param {string} siteId     e.g. 'MLA'
 * @param {object} siteData   the meli_sites entry (shop_url, credit_card_scenarios)
 * @param {object} guestUser  the country's guest buyer
 */
export function registerLuhnBlockScenario(siteId, siteData, guestUser) {
  const { shop_url, credit_card_scenarios } = siteData;
  const { APPROVED } = credit_card_scenarios;

  test.beforeEach(() => skipIfNotSite(test, siteId));

  test(`Given a Luhn-invalid card number in ${siteId}, When the order is placed, Then it is blocked with a card-specific error and never reaches order-received`, async ({ page }) => {
    test.setTimeout(120000);
    if (process.env.CHECKOUT === 'blocks') {
      await luhnBlockedBlocks(page, shop_url, guestUser, APPROVED.master, APPROVED.form);
    } else {
      await luhnBlockedClassic(page, shop_url, guestUser, APPROVED.master, APPROVED.form);
    }
  });
}
