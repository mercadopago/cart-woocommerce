import { expect, test } from "@playwright/test";
import { guestUserMLB } from "../../../data/buyer_data";
import { mlb } from "../../../data/meli_sites";
import {
    cleanupZeroDollarSubscriptionProduct,
    setupZeroDollarSubscriptionsEnvironment,
} from "../../../helpers/subscriptions-env";
import { wpEval, wpOption } from "../../../helpers/wp-env";
import { snapshotOptions, restoreOptions } from "../../../helpers/wp-options-snapshot";
import { fillBillingData } from "../../../flows/fill_steps_to_checkout";
import {
    fillCustomCardForm,
    placeOrder as placeOrderOnBlocks,
} from "../../../flows/chocustom";
import {
    addSubscriptionToCartAndCheckout,
    applySubscriptionCardEntryPatches,
    assertOrderReceived,
    fillClassicBillingForm,
    placeOrder as placeOrderOnClassic,
    selectMpCustomPaymentMethod,
    trackCorsErrors,
} from "../../../flows/subscriptions";
const { e2eTimeout } = require("../../../helpers/runtime-timeouts");

const { APPROVED } = mlb.credit_card_scenarios;
const origin = new URL(mlb.shop_url).origin;
const isBlocksCheckout = () => process.env.CHECKOUT === "blocks";

const WC_CLASSIC_CHECKOUT_REGEX = /wc-ajax=checkout/;
const WC_STORE_CHECKOUT_REGEX = /\/wc\/store\/v1\/checkout/;
const WC_STORE_CALC_TOTALS_FLAG = "__experimental_calc_totals";

function readPaymentData(paymentData, key) {
    const entry = Array.isArray(paymentData)
        ? paymentData.find((item) => item.key === key)
        : null;
    return entry?.value ?? "";
}

function readCustomCheckoutPayload(request, blocks) {
    const body = request.postData() || "";

    if (blocks) {
        const paymentData = body ? JSON.parse(body).payment_data : [];
        return {
            tokenPresent: Boolean(readPaymentData(paymentData, "mercadopago_custom[token]")),
            installments: String(readPaymentData(paymentData, "mercadopago_custom[installments]")),
        };
    }

    const params = new URLSearchParams(body);
    return {
        tokenPresent: Boolean(params.get("mercadopago_custom[token]")),
        installments: params.get("mercadopago_custom[installments]") ?? "",
    };
}

async function fillCardNumberOnly(page, cardNumber) {
    const cardNumberInput = page
        .frameLocator('iframe[name="cardNumber"]')
        .locator('[name="cardNumber"]');
    const cardDigits = cardNumber.replace(/\D/g, '');

    for (let attempt = 0; attempt < 3; attempt++) {
        await cardNumberInput.click({ timeout: e2eTimeout(15000) });
        await cardNumberInput.fill('');
        await cardNumberInput.pressSequentially(cardDigits, { delay: 50 });

        const enteredDigits = (await cardNumberInput.inputValue().catch(() => '')).replace(/\D/g, '');
        if (enteredDigits.length === cardDigits.length) {
            return;
        }
    }

    throw new Error('[E2E] Could not enter the complete card number for the untouched-holder assertion');
}

async function openZeroDollarSubscriptionCheckout(page, productUrl, email, blocks) {
    if (!blocks) {
        await addSubscriptionToCartAndCheckout(page, productUrl);
        await fillClassicBillingForm(page, guestUserMLB, email);
        await selectMpCustomPaymentMethod(page);
        await applySubscriptionCardEntryPatches(page);
        return;
    }

    await page.goto(productUrl, { waitUntil: "domcontentloaded" });
    await page.locator(".single_add_to_cart_button").click();
    await page.waitForLoadState("domcontentloaded");
    await page.goto(`${origin}/checkout/`, { waitUntil: "domcontentloaded" });
    await fillBillingData(page, { ...guestUserMLB, email });
    await selectMpCustomPaymentMethod(page);

    // Match the stable paid-subscription Blocks flow: wait for the Store API cart
    // recalculation before interacting with the Mercado Pago secure fields.
    await page.waitForTimeout(3000);
    await page.waitForLoadState("networkidle", { timeout: e2eTimeout(10000) }).catch(() => {});
}

test.describe("Initial subscription zero-dollar authorization (PSW-4486) @serial-store", () => {
    const STORE_OPTIONS = [
        "woocommerce_woo-mercado-pago-custom_settings",
        "woocommerce_subscriptions_accept_manual_renewals",
        "woocommerce_subscriptions_turn_off_automatic_payments",
    ];
    let storeSnapshot = null;
    let zeroDollarProduct = null;

    test.beforeAll(() => {
        test.skip(process.env.WP_EXTERNAL_STORE === "1", "The shared external stores do not allow test fixture mutation");
        test.skip(
            (process.env.MP_ENV || "test").toLowerCase() === "prod",
            "Zero-dollar authorization E2E runs only with sandbox subscription credentials"
        );
        if (!APPROVED.visa?.number) {
            throw new Error("[E2E] CC_VISA is not configured for the PSW-4486 sandbox scenario");
        }

        const hasEnvironmentCredentials = Boolean(
            process.env.MP_SUBSCRIPTIONS_ACCESS_TOKEN_TEST
            && process.env.MP_SUBSCRIPTIONS_PUBLIC_KEY_TEST
        );
        const hasStoredCredentials = wpEval(
            `$s = get_option("woocommerce_woo-mercado-pago-custom_settings", []);` +
            `echo (!empty($s["subscriptions_access_token_test"]) && !empty($s["subscriptions_public_key_test"])) ? "yes" : "no";`
        ) === "yes";
        test.skip(
            !hasEnvironmentCredentials && !hasStoredCredentials,
            "Sandbox Pre-approval access token/public key are not configured"
        );

        // The setup enables the recurring credential slots and automatic payments.
        // Restore the exact prior options so no store configuration leaks to other specs.
        storeSnapshot = snapshotOptions(STORE_OPTIONS);
        if (!storeSnapshot) {
            throw new Error("Could not snapshot the subscription gateway settings; refusing to mutate the store");
        }
        const automaticPaymentOptions = [
            "woocommerce_subscriptions_accept_manual_renewals",
            "woocommerce_subscriptions_turn_off_automatic_payments",
        ];
        const automaticPaymentResults = automaticPaymentOptions.map((option) => wpOption(option, "no"));
        if (automaticPaymentResults.some((result) => result === null)) {
            throw new Error("Could not enable automatic subscription payments for the ZDA fixture");
        }
        zeroDollarProduct = setupZeroDollarSubscriptionsEnvironment();
    });

    test.afterAll(() => {
        try {
            cleanupZeroDollarSubscriptionProduct(zeroDollarProduct);
        } finally {
            restoreOptions(storeSnapshot);
        }
    });

    test("Given an automatic subscription with a free trial, When the buyer pays with a valid card, Then installments stay fixed at one and the zero-dollar CIT completes", async ({ page }) => {
        test.setTimeout(e2eTimeout(120000));

        const blocks = isBlocksCheckout();
        const uniqueEmail = `test_user_psw4486_${Date.now()}@testuser.com`;
        const productUrl = origin + zeroDollarProduct.productPath;
        const corsErrors = trackCorsErrors(page);

        await openZeroDollarSubscriptionCheckout(page, productUrl, uniqueEmail, blocks);

        const amount = page.locator("#mp-amount");
        await expect(amount).toHaveCount(1);
        await expect(amount).toHaveAttribute("data-mp-cit-initial-context", "true");
        await expect.poll(async () => {
            const rawAmount = (await amount.inputValue()).trim().replace(",", ".");
            return rawAmount === "" ? null : Number(rawAmount);
        }, {
            timeout: e2eTimeout(10000),
            message: "the effective initial subscription amount should be zero",
        }).toBe(0);

        // The expected empty-installments TypeError is also forwarded through
        // CardForm's global onError callback. It must not validate an unrelated,
        // untouched holder-name field when the BIN is recognized.
        const installmentsResponsePromise = page.waitForResponse(
            response => response.url().includes('/v1/payment_methods/installments'),
            { timeout: e2eTimeout(15000) }
        );
        await fillCardNumberOnly(page, APPROVED.visa.number);
        await expect(page.locator('#paymentMethodId')).not.toHaveValue('', {
            timeout: e2eTimeout(15000),
        });
        const installmentsResponse = await installmentsResponsePromise;
        await installmentsResponse.finished();
        // The response body can finish before MercadoPago.js dispatches its callbacks.
        // Two render frames give that callback chain a deterministic turn before the
        // negative assertions below inspect the holder field.
        await page.evaluate(() => new Promise(resolve => {
            requestAnimationFrame(() => requestAnimationFrame(resolve));
        }));
        const untouchedHolderName = page.locator('#form-checkout__cardholderName');
        await expect(untouchedHolderName).not.toHaveClass(/mp-error/);
        await expect(untouchedHolderName).not.toHaveAttribute('aria-invalid', 'true');
        await expect(
            page.locator('#mp-card-holder-div input-helper[input-id="mp-card-holder-name-helper"] div')
        ).toBeHidden();

        await fillCustomCardForm(page, APPROVED.visa, APPROVED.form);

        // paymentMethodId is populated only after the SDK has recognised the BIN.
        // Assert the final public UI state after that async callback, not merely the
        // hidden field's template default.
        await expect(page.locator("#paymentMethodId")).not.toHaveValue("", {
            timeout: e2eTimeout(15000),
        });
        await expect(page.locator("#mp-checkout-custom-installments-card")).toHaveCount(1);
        await expect(page.locator("#mp-checkout-custom-installments-card")).toBeHidden();
        await expect(page.locator("#cardInstallments")).toHaveValue("1");
        await expect(page.locator(".woocommerce-error")).toHaveCount(0);

        const checkoutRequest = page.waitForRequest((request) => {
            if (request.method() !== "POST") return false;
            if (blocks) {
                return WC_STORE_CHECKOUT_REGEX.test(request.url())
                    && !request.url().includes(WC_STORE_CALC_TOTALS_FLAG);
            }
            return WC_CLASSIC_CHECKOUT_REGEX.test(request.url());
        }, { timeout: e2eTimeout(30000) });

        if (blocks) {
            await placeOrderOnBlocks(page);
        } else {
            await placeOrderOnClassic(page);
        }

        const payload = readCustomCheckoutPayload(await checkoutRequest, blocks);
        expect(payload.tokenPresent, "checkout should contain a generated card token").toBe(true);
        expect(payload.installments).toBe("1");

        await assertOrderReceived(page, corsErrors);
    });
});
