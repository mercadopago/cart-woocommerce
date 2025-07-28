import { test } from "@playwright/test";
import { mco } from "../../../../data/meli_sites";
import { modalCancelOrderTest, modalRejectAndChangeMethodTest } from "../../../../flows/chopro";

const { shop_url, credit_card_scenarios, guestUserDefault } = mco;
const { PENDING } = credit_card_scenarios;

test('Given a guest user, When their payment with chopro is pending and binary is on, Should show other payment options on change payment method', async ({page}) => {
  await modalRejectAndChangeMethodTest({
    page,
    url: shop_url,
    user: guestUserDefault,
    card: PENDING.master,
    form: PENDING.form
  });
})

test('Given a guest user, When their payment with chopro is pending and binary is on and they close the modal, Should show the cancelled order message', async ({page}) => {
  await modalCancelOrderTest({
    page,
    url: shop_url,
    user: guestUserDefault,
    card: PENDING.master,
    form: PENDING.form
  });
})
