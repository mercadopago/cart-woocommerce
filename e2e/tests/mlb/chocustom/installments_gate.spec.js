import { test } from "@playwright/test";
import { mlb } from "../../../data/meli_sites";
import { installmentsPlaceholderBlocksClassic } from "../../../flows/installments_gate";

/**
 * Desktop regression guard for the installments gate.
 *
 * The submit-time sync that mirrors the visible installments select into the hidden
 * posted field must not let an unselected installment slip through on desktop:
 * leaving the select on its placeholder must still block the submit with the
 * installments error.
 *
 * Classic-only: the assertion targets the Classic Custom installments error element.
 */

const { shop_url, credit_card_scenarios, guestUserMLB } = mlb;
const { APPROVED } = credit_card_scenarios;

test.skip(process.env.CHECKOUT === 'blocks', 'Installments gate guard targets the Classic Custom checkout');

test('Given the installments select left on its placeholder, When the order is placed, Then the submit is blocked with the installments error', async ({ page }) => {
  test.setTimeout(120000);
  await installmentsPlaceholderBlocksClassic(page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form);
});
