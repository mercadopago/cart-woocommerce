import { test } from "@playwright/test";
import { mlu } from "../../../data/meli_sites";
import { rejectedPaymentTest } from "../../../flows/chocustom";
const { skipIfNotSite } = require("../../../helpers/site-guard");
const { setGatewaySetting } = require("../../../helpers/wp-env");

const { shop_url, credit_card_scenarios, debit_card_scenarios, guestUser } = mlu;
const { PENDING: CREDIT_PENDING } = credit_card_scenarios;
const { PENDING: DEBIT_PENDING } = debit_card_scenarios;

test.beforeAll(() => {
  setGatewaySetting('woo-mercado-pago-custom', 'binary_mode', 'yes');
});

test.afterAll(() => {
  setGatewaySetting('woo-mercado-pago-custom', 'binary_mode', 'no');
});

test.beforeEach(() => {
  skipIfNotSite(test, 'MLU');
});

test('Given guest user with master credit card, When payment is pending and binary is on, Should show decline message', async ({page}) => {
  await rejectedPaymentTest(page, shop_url, guestUser, CREDIT_PENDING.master, CREDIT_PENDING.form);
});

test('Given guest user with amex credit card, When payment is pending and binary is on, Should show decline message', async ({page}) => {
  await rejectedPaymentTest(page, shop_url, guestUser, CREDIT_PENDING.amex, CREDIT_PENDING.form);
});

test('Given guest user with master debit card, When payment is pending and binary is on, Should show decline message', async ({page}) => {
  await rejectedPaymentTest(page, shop_url, guestUser, DEBIT_PENDING.master, DEBIT_PENDING.form);
});
