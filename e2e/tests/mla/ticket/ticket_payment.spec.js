import { test } from '@playwright/test';
import { mla } from "../../../data/meli_sites";
import { rejectedPaymentTest, successfulPaymentTest } from '../../../flows/ticket';

const { shop_url, guestUserMLA } = mla;

test('test invoice place order pago facil', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, guestUserMLA, 'Pago Fácil');
});

test('test invoice place order rapipago', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, guestUserMLA, 'Rapipago');
});

test('test invoice with invalid amount', async ({ page }) => {
  await rejectedPaymentTest(page, shop_url, guestUserMLA, 'Pago Fácil');
});
