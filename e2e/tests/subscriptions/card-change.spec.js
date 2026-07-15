import { test } from "@playwright/test";
import { guestUserMLB } from "../../data/buyer_data";
import { mlb } from "../../data/meli_sites";
import { setupSubscriptionsEnvironment, assertSubscriptionHasNewCard, getSubscriptionCardId } from "../../helpers/subscriptions-env";
import {
    trackCorsErrors,
    addSubscriptionToCartAndCheckout,
    fillClassicBillingForm,
    selectMpCustomPaymentMethod,
    applySubscriptionCardEntryPatches,
    fillMpSecureFields,
    fillInstallmentsForm,
    placeOrder,
    assertOrderReceived,
    getCustomerSubscriptionId,
    navigateToChangePaymentMethod,
    applyOrderPayCardEntryPatches,
    submitChangePaymentMethod,
} from "../../flows/subscriptions";

const { credit_card_scenarios } = mlb;
const { APPROVED } = credit_card_scenarios;

const origin     = new URL(process.env.SHOP_URL).origin;
const productUrl = origin + process.env.SUBSCRIPTION_PRODUCT_URL;

test.beforeAll(setupSubscriptionsEnvironment);

test("test successful payment method change as guest with master, new card must be saved on subscription", async ({ page }) => {
    // Subscription checkout is slower than a normal product checkout.
    test.setTimeout(120000);

    // Unique email per run — WCS creates a WP account on first subscription purchase.
    const uniqueEmail = `test_user_${Date.now()}@testuser.com`;

    const corsErrors = trackCorsErrors(page);

    // Step 1: Create an active subscription via CIT (prerequisite for card change).
    await addSubscriptionToCartAndCheckout(page, productUrl);
    await fillClassicBillingForm(page, guestUserMLB, uniqueEmail);
    await selectMpCustomPaymentMethod(page);
    await applySubscriptionCardEntryPatches(page);
    await fillMpSecureFields(page, APPROVED.visa);
    await fillInstallmentsForm(page, APPROVED.form);
    await placeOrder(page);
    await assertOrderReceived(page, corsErrors);

    // Step 2: Capture subscription ID and the current card ID (set by CIT) while
    // the customer session is still active. The original card ID is passed to
    // assertSubscriptionHasNewCard so it verifies the value actually changed,
    // not just that _mp_active_card_id is non-empty.
    const subscriptionId  = await getCustomerSubscriptionId(page);
    const originalCardId  = getSubscriptionCardId(subscriptionId);

    // Step 3: Navigate to the WCS "Change payment method" order-pay page and
    // fill a different card (master).
    await navigateToChangePaymentMethod(page, subscriptionId);
    await applyOrderPayCardEntryPatches(page);
    await fillMpSecureFields(page, APPROVED.master);
    await fillInstallmentsForm(page, APPROVED.form);

    // Step 4: Submit the change payment method form and verify server-side success.
    // handle3dsPayOrderFormSubmission() posts via jQuery.post and receives an HTTP 302
    // redirect from WC (not JSON), causing window.location.reload(). The browser-side
    // URL therefore stays on order-pay — success is confirmed via WP-CLI instead.
    await submitChangePaymentMethod(page);
    assertSubscriptionHasNewCard(subscriptionId, originalCardId);
});
