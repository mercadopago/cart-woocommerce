import { test } from "@playwright/test";
import { guestUserMLB } from "../../data/buyer_data";
import { mlb } from "../../data/meli_sites";
import { setupSubscriptionsEnvironment } from "../../helpers/subscriptions-env";
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
    loginToWpAdmin,
    getCustomerSubscriptionId,
    triggerSubscriptionRenewal,
    getRenewalOrderId,
    assertRenewalOrderProcessing,
} from "../../flows/subscriptions";

const { credit_card_scenarios } = mlb;
const { APPROVED } = credit_card_scenarios;

const origin     = new URL(process.env.SHOP_URL).origin;
const productUrl = origin + process.env.SUBSCRIPTION_PRODUCT_URL;

test.beforeAll(setupSubscriptionsEnvironment);

test("test successful subscription renewal as guest with visa, payment must be approved and renewal order must be processing", async ({ page }) => {
    // MIT renewal takes longer: CIT flow + WP admin navigation + renewal processing.
    test.setTimeout(120000);

    // Unique email per run — WCS creates a WP account on the first subscription
    // purchase, so reusing the same email triggers "account already exists" errors.
    const uniqueEmail = `test_user_${Date.now()}@testuser.com`;

    const corsErrors = trackCorsErrors(page);

    // Step 1: Create an active subscription via CIT (same flow as E2E-1).
    await addSubscriptionToCartAndCheckout(page, productUrl);
    await fillClassicBillingForm(page, guestUserMLB, uniqueEmail);
    await selectMpCustomPaymentMethod(page);
    await applySubscriptionCardEntryPatches(page);
    await fillMpSecureFields(page, APPROVED.visa);
    await fillInstallmentsForm(page, APPROVED.form);
    await placeOrder(page);
    await assertOrderReceived(page, corsErrors);

    // Step 2: Capture the subscription ID via the customer session — same pattern as
    // card-change.spec.js. Reading from My Account while the customer is still logged
    // in is more reliable than getLatestSubscriptionId (admin tr:first-child), which
    // can return the wrong subscription when multiple specs run in parallel.
    const subscriptionId = await getCustomerSubscriptionId(page);

    await loginToWpAdmin(page);

    // Step 3: Trigger MIT renewal via WP Admin → Subscription → Process renewal.
    await triggerSubscriptionRenewal(page, subscriptionId);

    // Step 4: Assert the renewal order was created with status "Processing",
    // confirming that the MIT intent was approved and payment_complete() was called.
    const renewalOrderId = await getRenewalOrderId(page, subscriptionId);
    await assertRenewalOrderProcessing(page, renewalOrderId);
});
