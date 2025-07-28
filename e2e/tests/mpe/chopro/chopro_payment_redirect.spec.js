import { test } from "@playwright/test";
import { mpe } from "../../../data/meli_sites";
import { redirectSuccessfulPaymentTest } from "../../../flows/chopro";

const { shop_url, credit_card_scenarios, guestUser } = mpe;
const { APPROVED } = credit_card_scenarios;

test('Given a guest user, When they complete a successful payment with chopro, Should show the success page', async ({ page }) => {
  await redirectSuccessfulPaymentTest({
    page,
    url: shop_url,
    user: guestUser,
    card: APPROVED.master,
    form: APPROVED.form
  });
})
