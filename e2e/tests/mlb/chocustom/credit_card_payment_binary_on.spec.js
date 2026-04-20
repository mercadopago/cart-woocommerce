import { test } from "@playwright/test";
import { mlb } from "../../../data/meli_sites";
import { rejectedPaymentTest } from "../../../flows/chocustom";
const { setGatewaySetting } = require("../../../helpers/wp-env");

const{ shop_url, credit_card_scenarios, guestUserMLB } = mlb;
const { PENDING } = credit_card_scenarios;

test.beforeAll(() => {
  setGatewaySetting('woo-mercado-pago-custom', 'binary_mode', 'yes');
});

test.afterAll(() => {
  setGatewaySetting('woo-mercado-pago-custom', 'binary_mode', 'no');
});

test('test pending payment as guest with master, payment must be rejected and decline message must be shown', async ({page}) => {
  await rejectedPaymentTest(page, shop_url, guestUserMLB, PENDING.master, PENDING.form);
});
