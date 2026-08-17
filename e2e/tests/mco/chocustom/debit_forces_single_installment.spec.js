import { test } from "@playwright/test";
import { mco } from "../../../data/meli_sites";
import { debitForcesSingleInstallment } from "../../../flows/installments_gate";

/**
 * Debit forces a single installment.
 *
 * A debit card must pin the installments to a single installment: the visible select
 * and the hidden posted field both become '1'. Runs in MCO, a market where debit is
 * available in the online checkout (MLB's checkout does not offer it).
 */

const { shop_url, debit_card_scenarios, guestUserMCO } = mco;
const { APPROVED } = debit_card_scenarios;

test.skip(process.env.CHECKOUT === 'blocks', 'Debit forced single-installment invariant targets the Classic Custom checkout');

test('Given a debit card, When it is identified at checkout, Then installments are forced to a single installment', async ({ page }) => {
  test.setTimeout(120000);
  await debitForcesSingleInstallment(page, shop_url, guestUserMCO, APPROVED.master, APPROVED.form);
});
