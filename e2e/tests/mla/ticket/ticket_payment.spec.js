import { test } from '@playwright/test';
import { mla } from "../../../data/meli_sites";
import { successfulPaymentTest } from '../../../flows/ticket';
const { skipIfNotSite } = require("../../../helpers/site-guard");

const { shop_url, guestUserMLA } = mla;

test.beforeEach(() => {
  skipIfNotSite(test, 'MLA');
});

test('test invoice place order pago facil', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, guestUserMLA, 'Pago Fácil');
});

test('test invoice place order rapipago', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, guestUserMLA, 'Rapipago');
});
