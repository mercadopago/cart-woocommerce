import { test } from "@playwright/test";
import { mlb } from "../../../data/meli_sites";
import { payOnOrderPayApproved, luhnBlockedOnOrderPay } from "../../../flows/order_pay";
const { e2eTimeout } = require("../../../helpers/runtime-timeouts");

/**
 * Order Pay page (form#order_review) — pay an existing pending order with the MP
 * Custom card gateway. Covers a valid payment and the Luhn block on this flow.
 *
 * Classic-only: the Order Pay page is a Classic WooCommerce flow (form#order_review);
 * it does not have a Blocks variant.
 */

const { shop_url, credit_card_scenarios, guestUserMLB } = mlb;
const { APPROVED } = credit_card_scenarios;

test.skip(process.env.CHECKOUT === 'blocks', 'Order Pay is a Classic-only flow (form#order_review)');
test.skip(
  process.env.WP_EXTERNAL_STORE === '1',
  'Order Pay setup requires an explicit remote WP-CLI control plane'
);

test('Given a pending order on the Order Pay page, When a valid card is submitted, Then the payment completes and the order-received page is shown', async ({ page }) => {
  test.setTimeout(e2eTimeout(120000));
  await payOnOrderPayApproved(page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form);
});

test('Given a pending order on the Order Pay page, When a Luhn-invalid card is submitted, Then it is blocked with a card-specific error and the order stays pending', async ({ page }) => {
  test.setTimeout(e2eTimeout(120000));
  await luhnBlockedOnOrderPay(page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form);
});
