#!/usr/bin/env bash

set -euo pipefail

WP=(/wp-cli.sh)
MP_MODE="${MP_MODE:-test}"
CHECKOUT_MODE="${CHECKOUT_MODE:-blocks}"
CREATE_ORDER_PAY_TEST_DATA="${CREATE_ORDER_PAY_TEST_DATA:-false}"

case "$MP_MODE" in
    test|production) ;;
    *) echo "[mp-dev] ERROR: modo do Mercado Pago inválido." >&2; exit 1 ;;
esac

case "$CHECKOUT_MODE" in
    blocks|classic|both) ;;
    *) echo "[mp-dev] ERROR: tipo de checkout inválido." >&2; exit 1 ;;
esac

case "$CREATE_ORDER_PAY_TEST_DATA" in
    true|false) ;;
    *) echo "[mp-dev] ERROR: opção de Order Pay inválida." >&2; exit 1 ;;
esac

configure_mercado_pago() {
    local public_key
    local access_token
    local test_mode

    if [ "$MP_MODE" = "production" ]; then
        public_key="${MP_PUBLIC_KEY_PROD:-}"
        access_token="${MP_ACCESS_TOKEN_PROD:-}"
        test_mode="no"
    else
        public_key="${MP_PUBLIC_KEY_TEST:-}"
        access_token="${MP_ACCESS_TOKEN_TEST:-}"
        test_mode="yes"
    fi

    if [ -z "$public_key" ] || [ -z "$access_token" ]; then
        echo "[mp-dev] ERROR: credenciais do Mercado Pago não configuradas para o modo escolhido." >&2
        exit 1
    fi

    if [ "$MP_MODE" = "production" ]; then
        "${WP[@]}" option update _mp_public_key_prod "$public_key" >/dev/null
        "${WP[@]}" option update _mp_access_token_prod "$access_token" >/dev/null
    else
        "${WP[@]}" option update _mp_public_key_test "$public_key" >/dev/null
        "${WP[@]}" option update _mp_access_token_test "$access_token" >/dev/null
    fi

    "${WP[@]}" option update checkbox_checkout_test_mode "$test_mode" >/dev/null
    echo "[mp-dev] Mercado Pago configurado em modo $MP_MODE."
}

configure_checkout_pages() {
    export CHECKOUT_MODE

    # The PHP source is fixed; the selected mode is validated above and passed
    # through the environment instead of being interpolated into the command.
    # shellcheck disable=SC2016
    "${WP[@]}" eval '
        $mode = getenv("CHECKOUT_MODE");
        $checkout_id = wc_get_page_id("checkout");

        $blocks = "<!-- wp:woocommerce/checkout -->"
            . "<div class=\"wp-block-woocommerce-checkout alignwide wc-block-checkout is-loading\">"
            . "<!-- wp:woocommerce/checkout-fields-block --><div class=\"wp-block-woocommerce-checkout-fields-block\">"
            . "<!-- wp:woocommerce/checkout-express-payment-block /-->"
            . "<!-- wp:woocommerce/checkout-contact-information-block /-->"
            . "<!-- wp:woocommerce/checkout-shipping-address-block /-->"
            . "<!-- wp:woocommerce/checkout-billing-address-block /-->"
            . "<!-- wp:woocommerce/checkout-shipping-methods-block /-->"
            . "<!-- wp:woocommerce/checkout-payment-block /-->"
            . "<!-- wp:woocommerce/checkout-additional-information-block /-->"
            . "<!-- wp:woocommerce/checkout-order-note-block /-->"
            . "<!-- wp:woocommerce/checkout-terms-block /-->"
            . "<!-- wp:woocommerce/checkout-actions-block /-->"
            . "</div><!-- /wp:woocommerce/checkout-fields-block -->"
            . "<!-- wp:woocommerce/checkout-totals-block --><div class=\"wp-block-woocommerce-checkout-totals-block\">"
            . "<!-- wp:woocommerce/checkout-order-summary-block --><div class=\"wp-block-woocommerce-checkout-order-summary-block\">"
            . "<!-- wp:woocommerce/checkout-order-summary-cart-items-block /-->"
            . "<!-- wp:woocommerce/checkout-order-summary-subtotal-block /-->"
            . "<!-- wp:woocommerce/checkout-order-summary-fee-block /-->"
            . "<!-- wp:woocommerce/checkout-order-summary-discount-block /-->"
            . "<!-- wp:woocommerce/checkout-order-summary-coupon-form-block /-->"
            . "<!-- wp:woocommerce/checkout-order-summary-shipping-block /-->"
            . "<!-- wp:woocommerce/checkout-order-summary-taxes-block /-->"
            . "<!-- wp:woocommerce/checkout-order-summary-totals-block /-->"
            . "</div><!-- /wp:woocommerce/checkout-order-summary-block -->"
            . "</div><!-- /wp:woocommerce/checkout-totals-block -->"
            . "</div><!-- /wp:woocommerce/checkout -->";
        $classic = "<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->";
        $main_content = $mode === "classic" ? $classic : $blocks;

        $result = wp_update_post(array("ID" => $checkout_id, "post_content" => $main_content), true);
        if (is_wp_error($result)) {
            fwrite(STDERR, "Could not configure the checkout page.\n");
            exit(1);
        }

        $extra = get_page_by_path("checkout-classic", OBJECT, "page");
        if ($mode === "both") {
            $page = array(
                "post_title" => "Checkout Classic",
                "post_name" => "checkout-classic",
                "post_type" => "page",
                "post_status" => "publish",
                "post_content" => $classic,
            );
            if ($extra) {
                $page["ID"] = $extra->ID;
                $result = wp_update_post($page, true);
            } else {
                $result = wp_insert_post($page, true);
            }
            if (is_wp_error($result)) {
                fwrite(STDERR, "Could not configure the additional checkout page.\n");
                exit(1);
            }
        } elseif ($extra && $extra->post_status === "publish") {
            wp_update_post(array("ID" => $extra->ID, "post_status" => "draft"));
        }
    '

    echo "[mp-dev] Checkout configurado: $CHECKOUT_MODE."
}

create_order_pay_data() {
    if [ "$CREATE_ORDER_PAY_TEST_DATA" != "true" ]; then
        return
    fi

    if [ -z "${TEST_CUSTOMER_USERNAME:-}" ] || [ -z "${TEST_CUSTOMER_EMAIL:-}" ] || [ -z "${TEST_CUSTOMER_PASSWORD:-}" ]; then
        echo "[mp-dev] ERROR: configure usuário, e-mail e senha do cliente de teste no .env." >&2
        exit 1
    fi

    export TEST_CUSTOMER_USERNAME TEST_CUSTOMER_EMAIL TEST_CUSTOMER_PASSWORD

    # shellcheck disable=SC2016
    "${WP[@]}" eval '
        $raw_username = getenv("TEST_CUSTOMER_USERNAME");
        $username = sanitize_user($raw_username, true);
        $email = sanitize_email(getenv("TEST_CUSTOMER_EMAIL"));
        $password = getenv("TEST_CUSTOMER_PASSWORD");

        if ($username === "" || $username !== $raw_username || !is_email($email) || !is_string($password) || $password === "") {
            fwrite(STDERR, "Invalid local test customer configuration.\n");
            exit(1);
        }

        $user = get_user_by("login", $username);
        $email_user = get_user_by("email", $email);
        if (!$user && $email_user) {
            fwrite(STDERR, "The local test customer email already belongs to another account.\n");
            exit(1);
        }

        if (!$user) {
            $user_id = wp_create_user($username, $password, $email);
            if (is_wp_error($user_id)) {
                fwrite(STDERR, "Could not create the local test customer.\n");
                exit(1);
            }
            $user = get_user_by("id", $user_id);
            update_user_meta($user->ID, "_mp_dev_order_pay_test_customer", "yes");
        } elseif ($user->user_email !== $email || get_user_meta($user->ID, "_mp_dev_order_pay_test_customer", true) !== "yes") {
            fwrite(STDERR, "The local test username is already used by another account.\n");
            exit(1);
        } else {
            wp_set_password($password, $user->ID);
        }

        $user->set_role("customer");
        $order_id = (int) get_user_meta($user->ID, "_mp_dev_order_pay_order_id", true);
        $order = $order_id ? wc_get_order($order_id) : false;

        if (!$order || !$order->has_status("pending")) {
            $products = wc_get_products(array("status" => "publish", "limit" => 1, "orderby" => "ID", "order" => "ASC"));
            if (!$products) {
                fwrite(STDERR, "No product is available for the local test order.\n");
                exit(1);
            }

            $location = wc_get_base_location();
            $order = wc_create_order(array("customer_id" => $user->ID));
            if (is_wp_error($order)) {
                fwrite(STDERR, "Could not create the local test order.\n");
                exit(1);
            }
            $order->add_product($products[0], 1);
            $order->set_billing_email($email);
            $order->set_billing_country($location["country"]);
            $order->set_billing_state($location["state"]);
            $order->calculate_totals();
            $order->set_status("pending");
            $order->save();
            update_user_meta($user->ID, "_mp_dev_order_pay_order_id", $order->get_id());
        }
    '

    echo "[mp-dev] Cliente e pedido pendente para Order Pay criados."
}

configure_mercado_pago
configure_checkout_pages
create_order_pay_data
