import { test } from "@playwright/test";
import { mlb } from "../../../data/meli_sites";
import {
  assertInvalidSecurityFieldsMetric,
  assertInvalidCardholderFieldsMetric,
  assertRealErrorNotCategorized,
} from "../../../flows/tokenization_observability";

/**
 * createCardToken field-error observability.
 *
 * Asserts the `mp_api_error` metric beacon (…/monitor/v1/event/datadog/big/mp_api_error)
 * carries the right classification:
 *   - invalid_security_fields   (empty CVV -> secure field rejected)
 *   - invalid_cardholder_fields (empty cardholder name -> non-PCI rejection)
 *   - a REAL network error stays uncategorized (no reason, not a field category)
 *
 * The metric is a navigator.sendBeacon POST; we stub the beacon so it never leaves
 * the browser and observe its payload. This exercises the real SDK/plugin path — no
 * token is injected and nothing is monkey-patched.
 */

const { shop_url, credit_card_scenarios, guestUserMLB } = mlb;
const { APPROVED } = credit_card_scenarios;

test('Given a card with an empty CVV, When the order is placed, Then the tokenization metric reports invalid_security_fields with the securityCode reason', async ({ page }) => {
  test.setTimeout(120000);
  await assertInvalidSecurityFieldsMetric(page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form);
});

test('Given a card with an empty cardholder name, When the order is placed, Then the tokenization metric reports invalid_cardholder_fields with a reason', async ({ page }) => {
  test.setTimeout(120000);
  await assertInvalidCardholderFieldsMetric(page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form);
});

test('Given a real network failure on card tokenization, When the order is placed, Then the tokenization metric is not categorized as a field error', async ({ page }) => {
  test.setTimeout(120000);
  await assertRealErrorNotCategorized(page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form);
});
