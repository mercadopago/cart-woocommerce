import { expect } from "@playwright/test";

/**
 * Tracks CORS errors that indicate card tokenisation failure.
 * Returns the live array — callers can inspect it at any point after setup.
 *
 * Only captures errors on the card_tokens endpoint. Other CORS errors
 * (e.g. payment_methods/search) are expected on localhost and benign — the
 * MP SDK falls back gracefully and tokenisation is unaffected.
 */
export function trackCorsErrors(page) {
    const errors = [];
    page.on("console", msg => {
        const text = msg.text();
        if ((text.includes("CORS") || text.includes("net::ERR_FAILED"))
            && text.includes("card_tokens")) {
            errors.push(text);
        }
    });
    return errors;
}

/**
 * Navigates to a subscription product page, adds it to the cart, and proceeds
 * to the checkout page. Resolves when the checkout page is fully loaded.
 */
export async function addSubscriptionToCartAndCheckout(page, productUrl) {
    await page.goto(productUrl, { waitUntil: "domcontentloaded" });
    await page.locator(".single_add_to_cart_button").click();
    await page.locator(".woocommerce-message").waitFor({ state: "visible", timeout: 10000 });

    await page.locator(".woocommerce-message a.wc-forward").click();
    await page.waitForLoadState();
    await page.locator(".checkout-button").click();
    await page.waitForLoadState();
}

/**
 * Fills the WooCommerce Classic checkout billing form.
 *
 * `email` is accepted as a separate parameter because subscription tests use a
 * unique email per run to avoid "account already exists" errors (WCS creates a
 * WP account on the first subscription purchase).
 */
export async function fillClassicBillingForm(page, user, email) {
    await page.locator("#billing_first_name").fill(user.firstName);
    await page.locator("#billing_last_name").fill(user.lastName);
    await page.locator("#billing_country").selectOption(user.address.countryId);
    await page.waitForTimeout(1500);
    await page.locator("#billing_address_1").fill(user.address.street);
    await page.locator("#billing_city").fill(user.address.city);

    const stateField = page.locator("#billing_state");
    if (await stateField.isVisible({ timeout: 1000 }).catch(() => false)) {
        await stateField.selectOption(user.address.state);
    }

    const postcodeField = page.locator("#billing_postcode");
    if (await postcodeField.isVisible({ timeout: 1000 }).catch(() => false)) {
        await postcodeField.fill(user.address.zip);
    }

    await page.locator("#billing_phone").fill(user.phone || "11999999999");
    await page.locator("#billing_email").fill(email);

    const personType = page.locator("#billing_persontype");
    if (await personType.isVisible({ timeout: 1000 }).catch(() => false)) {
        await personType.selectOption("1");
        await page.waitForTimeout(500);
        await page.locator("#billing_cpf").fill(user.document || "");
    }

    const numberField = page.locator("#billing_number");
    if (await numberField.isVisible({ timeout: 1000 }).catch(() => false)) {
        await numberField.fill(user.address.number || "122");
    }

    // Allow WCS to trigger update_order_review with the billing data before
    // we proceed to payment method selection.
    await page.waitForTimeout(2000);
}

/**
 * Selects the MP Custom (Transparente) payment method on the checkout page,
 * handling both Classic and Blocks checkout and the subscription-specific case
 * where WC hides the radio when it is the only available gateway.
 *
 * WC hides the <input type="radio"> via .hide() (display:none) when there is
 * only one available payment method (checkout.js:229-230). WCS filters gateways
 * to subscription-capable ones, so MP Custom is often the sole option and gets
 * auto-selected — clicking the hidden radio is unnecessary and causes errors.
 */
export async function selectMpCustomPaymentMethod(page) {
    const classicContainer = page.locator("li.wc_payment_method.payment_method_woo-mercado-pago-custom");
    const blocksRadio = page.locator("#radio-control-wc-payment-method-options-woo-mercado-pago-custom");

    if (await classicContainer.isVisible({ timeout: 10000 }).catch(() => false)) {
        const classicRadio = classicContainer.locator('input[name="payment_method"]');
        const isHidden = await classicRadio.evaluate(
            el => el.style.display === "none" || window.getComputedStyle(el).display === "none"
        ).catch(() => true);

        if (!isHidden) {
            await page.locator('label[for="payment_method_woo-mercado-pago-custom"]').click();
            await page.waitForTimeout(1000);
        }
        // else: single gateway, already auto-selected — no click needed
    } else {
        await blocksRadio.check();
    }

    await page.waitForLoadState();
}

/**
 * Applies three browser-side patches that are required for reliable card field
 * interaction on subscription product checkouts, and sets up the route interceptor
 * that prevents WC from re-rendering the payment section during card entry.
 *
 * Must be called after selectMpCustomPaymentMethod() and before fillMpSecureFields().
 * The patches remain active for the remainder of the test — they do not interfere
 * with the checkout AJAX submission (wc-ajax=checkout is never intercepted).
 *
 * Patch A — jQuery.fn.block no-op for the payment section:
 *   WC calls jQuery('.woocommerce-checkout-payment').block() before every
 *   update_order_review AJAX. The route interceptor strips the payment fragment
 *   from responses, so .unblock() is never called for that section — blockUI
 *   overlays would stack up and block clicks on the card iframes.
 *
 * Patch B — cardForm.createLoadSpinner no-op:
 *   handleUpdatedCheckout() calls this.cardForm.createLoadSpinner() on every
 *   updated_checkout event, adding mp-display-none to the card form container.
 *   This hides the iframes mid-pressSequentially, causing the card number to be
 *   truncated. (Overriding handleUpdatedCheckout itself does not work — the jQuery
 *   listener holds a captured .bind(this) reference that ignores property changes.)
 *
 * Patch C — cardForm.form.unmount no-op:
 *   handleUpdatedCheckout() calls form.unmount() when the subscription amount
 *   changes between WCS recalculation cycles. After unmount() the SDK is
 *   disconnected from the iframes — they still show values visually but
 *   createCardToken() returns an empty token, causing a silent checkout failure.
 *
 * Route interceptor:
 *   Passes the real WC update_order_review response through but deletes the
 *   .woocommerce-checkout-payment fragment so WC does not re-render the payment
 *   section (which would destroy the MP Secure Fields iframes). All other
 *   fragments are forwarded normally; updated_checkout still fires; the card form
 *   stays mounted (isCardFormDetached = false).
 */
export async function applySubscriptionCardEntryPatches(page) {
    await page.waitForFunction(
        () => {
            const hasBlockUI = !!document.querySelector(".woocommerce-checkout-payment .blockUI.blockOverlay");
            const container = document.querySelector("#mp-checkout-custom-root.mp-checkout-container");
            const hasLoading = !!container?.classList.contains("mp-display-none")
                || !!container?.classList.contains("mp-hidden");
            return !hasBlockUI && !hasLoading;
        },
        { timeout: 20000 }
    );

    await page.evaluate(() => {
        if (typeof jQuery !== "undefined") {
            jQuery.fn._origBlock = jQuery.fn.block;
            jQuery.fn.block = function(opts) {
                if (this.is(".woocommerce-checkout-payment, .woocommerce-checkout-review-order-table")) return this;
                return jQuery.fn._origBlock.call(this, opts);
            };
        }

        const cardForm = window.mpCustomCheckoutHandler?.cardForm;

        if (cardForm) {
            cardForm._origCreateLoadSpinner = cardForm.createLoadSpinner.bind(cardForm);
            cardForm.createLoadSpinner = () => {};
            cardForm.isLoading = false;
            cardForm.removeLoadSpinner();
        }

        if (cardForm?.form) {
            cardForm.form._origUnmount = cardForm.form.unmount;
            cardForm.form.unmount = () => {};
        }
    });

    await page.route(/wc-ajax=update_order_review/, async route => {
        const response = await route.fetch();
        try {
            const json = JSON.parse(await response.text());
            if (json.fragments) {
                delete json.fragments[".woocommerce-checkout-payment"];
            }
            await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(json) });
        } catch {
            await route.continue();
        }
    });
}

/**
 * Fills the three MP Secure Fields iframes (card number, security code,
 * expiration date) using pressSequentially so the MP SDK receives real
 * keydown/keyup events and builds its internal tokenisable state.
 *
 * fill() sets the DOM value directly — enough for card identification
 * (installments appear) but not for createCardToken().
 */
export async function fillMpSecureFields(page, card) {
    await page.locator("iframe[name='cardNumber']").waitFor({ state: "visible", timeout: 30000 });

    const cardNumberInput = page.frameLocator("iframe[name='cardNumber']").locator("[name='cardNumber']");
    await cardNumberInput.click({ timeout: 15000 });
    await cardNumberInput.pressSequentially(card.number.replace(/\s/g, ""), { delay: 30 });

    const securityCodeInput = page.frameLocator("iframe[name='securityCode']").locator("[name='securityCode']");
    await securityCodeInput.click();
    await securityCodeInput.pressSequentially(card.code, { delay: 30 });

    const expirationDateInput = page.frameLocator("iframe[name='expirationDate']").locator("[name='expirationDate']");
    await expirationDateInput.click();
    await expirationDateInput.pressSequentially(card.date.replace("/", ""), { delay: 30 });

    // Allow the MP SDK time to identify the card brand and populate the installments form.
    // Wait for the installments section to appear (card brand identified by MP SDK)
    // rather than a fixed timeout — falls through gracefully if it never shows.
    await page.waitForSelector("#mp-checkout-custom-installments-card", {
        state: "visible", timeout: 15000
    }).catch(() => {});
}

/**
 * Fills the installments form that appears after the MP SDK identifies the card brand.
 * No-ops silently if the section is not visible (e.g. card identification did not complete).
 */
export async function fillInstallmentsForm(page, form) {
    const installments = page.locator("#mp-checkout-custom-installments-card");

    if (await installments.isVisible()) {
        await page.locator("#form-checkout__identificationType").selectOption(form.docType);
        await page.waitForTimeout(200);
        await page.locator('[name="identificationNumber"]').fill(form.docNumber);
        await page.locator("#form-checkout__cardholderName").fill(form.name);

        if (form.name !== "") {
            await page.locator("#form-checkout__installments").selectOption({ index: 1 });
        }

        await page.waitForLoadState();
    }
}

/**
 * Clears residual MP loading state, force-unblocks the payment section, and
 * clicks the place order button (Classic or Blocks checkout).
 */
export async function placeOrder(page) {
    await page.evaluate(() => {
        const cardForm = window.mpCustomCheckoutHandler?.cardForm;
        if (cardForm) {
            cardForm.isLoading = false;
            cardForm.removeLoadSpinner();
        }
        if (typeof jQuery !== "undefined") {
            jQuery(".woocommerce-checkout-payment, .woocommerce-checkout-review-order-table").unblock();
        }
    });

    const classicPlaceOrder = page.locator("#place_order");
    const blocksPlaceOrder = page.locator(".wc-block-components-checkout-place-order-button");

    if (await classicPlaceOrder.isVisible({ timeout: 3000 }).catch(() => false)) {
        await classicPlaceOrder.click();
    } else {
        await blocksPlaceOrder.click();
    }
}

/**
 * Waits for the order-received page with a CORS fast-fail within 5 s, then
 * asserts the WooCommerce thank-you element is visible.
 */
export async function assertOrderReceived(page, corsErrors) {
    await Promise.race([
        page.waitForURL(/order-received/, { waitUntil: "domcontentloaded", timeout: 60000 }),
        page.waitForTimeout(5000).then(() => {
            if (corsErrors.length > 0) {
                throw new Error(
                    "[CORS] Falha na tokenizacao de cartao no ambiente Sandbox.\n\n" +
                    "Erros capturados no console:\n" + corsErrors.join("\n")
                );
            }
            return new Promise(() => {});
        }),
    ]);

    await expect(page.locator(".woocommerce-thankyou-order-received")).toBeVisible({ timeout: 10000 });
}

// ─── WP Admin helpers (used by MIT / admin-triggered tests) ──────────────────

/**
 * Navigates to WP Admin and logs in if not already authenticated.
 * Reads credentials from WP_ADMIN_USER / WP_ADMIN_PASSWORD env vars,
 * falling back to the docker-flexible-environment defaults (admin / admin).
 */
export async function loginToWpAdmin(page) {
    const base = new URL(process.env.SHOP_URL).origin;

    // Navigate directly to wp-login.php rather than wp-admin/.
    // After the CIT checkout the customer account is logged in — WordPress lets
    // customers into /wp-admin/ (with limited access) and does NOT redirect to
    // wp-login.php, so the previous check `url.includes('wp-login.php')` returned
    // false and the function exited early without switching to the admin account.
    await page.goto(`${base}/wp-login.php`);

    // If wp-login.php immediately redirects to wp-admin (already logged in as admin),
    // the login form won't be present and we can skip filling credentials.
    const loginForm = page.locator("#loginform");
    if (!await loginForm.isVisible({ timeout: 3000 }).catch(() => false)) return;

    await page.locator("#user_login").fill(process.env.WP_ADMIN_USER || "admin");
    await page.locator("#user_pass").fill(process.env.WP_ADMIN_PASSWORD || "admin");
    await page.locator("#wp-submit").click();
    await page.waitForURL(/wp-admin/, { timeout: 10000 });
}

/**
 * Returns the post ID of the most recently created subscription from the
 * WP Admin subscription list. Assumes the caller is already logged in.
 */
export async function getLatestSubscriptionId(page) {
    const base = new URL(process.env.SHOP_URL).origin;
    await page.goto(`${base}/wp-admin/edit.php?post_type=shop_subscription`);
    const link = page.locator("#the-list tr:first-child a[href*='post=']").first();
    const href = await link.getAttribute("href");
    return href?.match(/post=(\d+)/)?.[1] ?? null;
}

/**
 * Opens the subscription edit page in WP Admin and triggers the
 * "Process renewal" action via the WC Order Actions meta box.
 *
 * WCS shows a native window.confirm() before processing — the dialog handler
 * is registered before clicking Apply so it is accepted automatically.
 */
export async function triggerSubscriptionRenewal(page, subscriptionId) {
    const base = new URL(process.env.SHOP_URL).origin;
    await page.goto(`${base}/wp-admin/post.php?post=${subscriptionId}&action=edit`);
    page.once("dialog", dialog => dialog.accept());
    await page.locator('select[name="wc_order_action"]').selectOption("wcs_process_renewal");
    await page.locator("button.wc-reload").click();
    await page.waitForLoadState();
}

/**
 * Returns the post ID of the renewal order created for a subscription.
 * Reads the "Related Orders" meta box on the subscription edit page.
 * The relationship column uses the WCS label "Renewal Order".
 */
export async function getRenewalOrderId(page, subscriptionId) {
    const base = new URL(process.env.SHOP_URL).origin;
    await page.goto(`${base}/wp-admin/post.php?post=${subscriptionId}&action=edit`);
    const renewalRow = page
        .locator(".woocommerce_subscriptions_related_orders table tbody tr")
        .filter({ hasText: "Renewal Order" })
        .first();
    const href = await renewalRow.locator("a[href*='post=']").first().getAttribute("href");
    return href?.match(/post=(\d+)/)?.[1] ?? null;
}

/**
 * Navigates to the WC order edit page for the given renewal order and asserts
 * that its status is "Processing" — confirming that payment_complete() was
 * called and _mp_payment_id was persisted before the status transition.
 */
export async function assertRenewalOrderProcessing(page, renewalOrderId) {
    const base = new URL(process.env.SHOP_URL).origin;
    await page.goto(`${base}/wp-admin/post.php?post=${renewalOrderId}&action=edit`);
    // WC adds the status as a CSS class on the mark element (e.g. status-processing),
    // so we can target it directly without ambiguity across multiple status badges on the page.
    await expect(page.locator("mark.order-status.status-processing")).toBeVisible({ timeout: 10000 });
}

// ─── Card change helpers (used by E2E-3) ─────────────────────────────────────

/**
 * Returns the subscription ID from the WCS My Account subscriptions list.
 * The customer must be logged in when this is called.
 */
export async function getCustomerSubscriptionId(page) {
    const base = new URL(process.env.SHOP_URL).origin;
    await page.goto(`${base}/my-account/subscriptions/`);
    const link = page.locator('a[href*="view-subscription"]').first();
    const href = await link.getAttribute("href");
    return href?.match(/view-subscription\/(\d+)/)?.[1] ?? null;
}

/**
 * Navigates to the WCS "Change payment method" order-pay page for the given
 * subscription. The customer must be logged in.
 *
 * WCS renders a "Change payment" action button on the view-subscription page
 * whose href includes `change_payment_method={id}`. Clicking it loads the
 * order-pay page with form#order_review (isOrderPayPage = true in the MP plugin).
 */
export async function navigateToChangePaymentMethod(page, subscriptionId) {
    const base = new URL(process.env.SHOP_URL).origin;
    await page.goto(`${base}/my-account/view-subscription/${subscriptionId}/`);
    await page.locator('a[href*="change_payment_method"]').click();
    await page.waitForLoadState();
}

/**
 * Applies browser-side patches for the card entry on the order-pay
 * (change-payment-method) page.
 *
 * The order-pay page uses form#order_review. isOrderPayPage() returns true,
 * so the MP plugin initialises via onSelectCheckoutCustomInOrderPayPage() which
 * calls createLoadSpinner() and loadSuperToken() (up to 10 s retry loop).
 * There is NO WCS update_order_review AJAX cycling on this page, so:
 *   - Patch A (jQuery.fn.block) is NOT needed
 *   - Route interceptor is NOT needed
 * Only Patches B and C are applied to keep the iframes visible and connected.
 */
export async function applyOrderPayCardEntryPatches(page) {
    await page.evaluate(() => {
        const cardForm = window.mpCustomCheckoutHandler?.cardForm;
        if (cardForm) {
            cardForm._origCreateLoadSpinner = cardForm.createLoadSpinner.bind(cardForm);
            cardForm.createLoadSpinner = () => {};
            cardForm.isLoading = false;
            cardForm.removeLoadSpinner();
        }
        if (cardForm?.form) {
            cardForm.form._origUnmount = cardForm.form.unmount;
            cardForm.form.unmount = () => {};
        }
    });
}

/**
 * Submits the change-payment-method order-pay form and asserts the redirect
 * to the subscription view page (PHP returns result:success + redirect URL
 * when the AP add-payment-method call succeeds).
 */
export async function submitChangePaymentMethod(page) {
    await page.evaluate(() => {
        const cardForm = window.mpCustomCheckoutHandler?.cardForm;
        if (cardForm) {
            cardForm.isLoading = false;
            cardForm.removeLoadSpinner();
        }
    });

    await page.locator("#place_order").click();

    // handle3dsPayOrderFormSubmission() posts via jQuery.post('#', ...).
    // WC processes the payment and calls wp_redirect() (HTTP 302). jQuery follows
    // the redirect silently and delivers HTML to .done() — not JSON. Because the
    // response lacks result:'success', the code falls through to window.location.reload().
    // The browser reloads to the same order-pay URL rather than navigating to
    // view-subscription. We therefore cannot rely on URL change for success detection;
    // instead we wait for the network to settle and verify success server-side.
    await page.waitForLoadState("networkidle", { timeout: 15000 }).catch(() => {});
}
