import { test } from "@playwright/test";
import { mlb } from "../../../data/meli_sites";
import {
  luhnBlockedClassic,
  luhnBlockedBlocks,
  luhnPreservesInstallmentsClassic,
} from "../../../flows/luhn_validation";

/**
 * Luhn (checksum) validation in the Custom Checkout (card).
 *
 * The card number field runs Luhn validation, so a plausible number (valid brand +
 * length) that fails the checksum is blocked client-side before tokenization, with
 * a CARD-specific error (not the generic form error).
 *
 * The Luhn-valid happy path (a fully valid number is APPROVED) is already covered by
 * checkout_validation_gate.spec.js and the binary_off specs, so it is not duplicated
 * here.
 */

const { shop_url, credit_card_scenarios, guestUserMLB } = mlb;
const { APPROVED } = credit_card_scenarios;

test('Given a Luhn-invalid card number, When the order is placed, Then it is blocked with a card-specific error and never reaches order-received', async ({ page }) => {
  test.setTimeout(120000);
  if (process.env.CHECKOUT === 'blocks') {
    await luhnBlockedBlocks(page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form);
  } else {
    await luhnBlockedClassic(page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form);
  }
});

test('Given a valid card with installments loaded, When the last digit is changed to fail Luhn, Then the loaded installments are preserved', async ({ page }) => {
  test.skip(process.env.CHECKOUT === 'blocks', 'Installments preservation is a Classic-checkout scenario');
  test.setTimeout(120000);
  await luhnPreservesInstallmentsClassic(page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form);
});
