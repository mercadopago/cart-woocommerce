#!/bin/bash
# ============================================================================
# Country-specific WooCommerce store configuration.
# Called by entrypoint.sh with the SITE code (mlb, mla, mlm, etc.)
#
# For each country this script configures:
#   1. Currency, locale, decimal/thousand separators
#   2. Store address (country, state, city, postcode)
#   3. Tax rates (VAT/IVA/ICMS)
#   4. Shipping zone with flat rate
#   5. Products with local names and realistic prices
#   6. Country-specific plugins (e.g. CPF/CNPJ for Brazil)
# ============================================================================

set -euo pipefail

SITE="${1:-mlb}"
WP="wp --allow-root --path=/var/www/html"

# ============================================================================
# Country configurations
# ============================================================================

configure_currency() {
    local currency="$1" pos="$2" thousand="$3" decimal="$4" decimals="$5"
    $WP option update woocommerce_currency "$currency"
    $WP option update woocommerce_currency_pos "$pos"
    $WP option update woocommerce_price_thousand_sep "$thousand"
    $WP option update woocommerce_price_decimal_sep "$decimal"
    $WP option update woocommerce_price_num_decimals "$decimals"
}

configure_location() {
    local country="$1" address="$2" city="$3" postcode="$4"
    $WP option update woocommerce_default_country "$country"
    $WP option update woocommerce_store_address "$address"
    $WP option update woocommerce_store_city "$city"
    $WP option update woocommerce_store_postcode "$postcode"
}

configure_tax() {
    local country_code="$1" rate="$2" name="$3"
    $WP option update woocommerce_calc_taxes yes
    $WP option update woocommerce_prices_include_tax no
    $WP option update woocommerce_tax_display_shop excl
    $WP option update woocommerce_tax_display_cart excl

    $WP eval "
        WC_Tax::_insert_tax_rate(array(
            'tax_rate_country'  => '$country_code',
            'tax_rate_state'    => '',
            'tax_rate'          => '$rate',
            'tax_rate_name'     => '$name',
            'tax_rate_priority' => 1,
            'tax_rate_compound' => 0,
            'tax_rate_shipping' => 1,
            'tax_rate_order'    => 0,
            'tax_rate_class'    => '',
        ));
    "
}

configure_shipping() {
    local zone_name="$1" country_code="$2" cost="$3"
    $WP eval "
        \$zone = new WC_Shipping_Zone();
        \$zone->set_zone_name('$zone_name');
        \$zone->set_zone_order(0);
        \$zone->save();
        \$instance_id = \$zone->add_shipping_method('flat_rate');
        update_option('woocommerce_flat_rate_' . \$instance_id . '_settings', array(
            'title'      => 'Flat Rate',
            'tax_status' => 'taxable',
            'cost'       => '$cost',
        ));
        \$zone->add_location('$country_code', 'country');
        \$zone->save();
    "
}

create_product() {
    local name="$1" price="$2"
    $WP wc product create \
        --name="$name" \
        --regular_price="$price" \
        --status=publish \
        --type=simple \
        --manage_stock=false \
        --user=admin 2>/dev/null || {
        # Fallback: wp post create if wc cli fails
        local id
        id=$($WP post create --post_type=product --post_title="$name" --post_status=publish --porcelain)
        $WP post meta update "$id" _regular_price "$price"
        $WP post meta update "$id" _price "$price"
        $WP post meta update "$id" _stock_status "instock"
    }
}

install_locale() {
    local locale="$1"
    $WP language core install "$locale" 2>/dev/null || true
    $WP site switch-language "$locale" 2>/dev/null || true
}

enable_gateways() {
    # Enable specified MP gateways. Each arg is a gateway ID.
    for gw_id in "$@"; do
        $WP eval "update_option('woocommerce_${gw_id}_settings', array('enabled' => 'yes'));"
        echo "[mp-dev]   Gateway enabled: $gw_id"
    done
}

configure_mp_credentials() {
    # Configure MP credentials from environment variables.
    # These are passed via docker-compose.yml from the host .env file.
    if [ -n "${MP_ACCESS_TOKEN_TEST:-}" ]; then
        $WP option update _mp_access_token_test "$MP_ACCESS_TOKEN_TEST"
        echo "[mp-dev]   Test access token configured"
    fi
    if [ -n "${MP_PUBLIC_KEY_TEST:-}" ]; then
        $WP option update _mp_public_key_test "$MP_PUBLIC_KEY_TEST"
        echo "[mp-dev]   Test public key configured"
    fi
    if [ -n "${MP_ACCESS_TOKEN_PROD:-}" ]; then
        $WP option update _mp_access_token_prod "$MP_ACCESS_TOKEN_PROD"
        echo "[mp-dev]   Prod access token configured"
    fi
    if [ -n "${MP_PUBLIC_KEY_PROD:-}" ]; then
        $WP option update _mp_public_key_prod "$MP_PUBLIC_KEY_PROD"
        echo "[mp-dev]   Prod public key configured"
    fi

    # Custom domain for notification_url (webhooks).
    # Without this, notification_url may be null and payments fail.
    if [ -n "${MP_CUSTOM_DOMAIN:-}" ]; then
        $WP option update _mp_custom_domain "$MP_CUSTOM_DOMAIN"
        echo "[mp-dev]   Custom domain set: $MP_CUSTOM_DOMAIN"
    fi

    # Enable test/sandbox mode
    $WP option update checkbox_checkout_test_mode yes 2>/dev/null || true
}

# ============================================================================
# Per-country setup
# ============================================================================

case "$SITE" in

    # ========================================================================
    # MLB — Brazil
    # ========================================================================
    mlb)
        install_locale "pt_BR"
        configure_currency "BRL" "left_space" "." "," "2"
        configure_location "BR:SP" "Rua Augusta, 1000" "Sao Paulo" "01304-000"
        configure_tax "BR" "18.0000" "ICMS"
        configure_shipping "Brasil" "BR" "15.00"

        create_product "Camiseta Basica" "59.90"
        create_product "Tenis Esportivo" "189.90"
        create_product "Mochila Urban" "129.90"
        create_product "Relogio Digital" "299.90"
        create_product "Fone Bluetooth" "79.90"

        # Brazil-specific: CPF/CNPJ checkout fields
        echo "[mp-dev] Installing Brazil checkout fields plugin..."
        $WP plugin install /usr/src/wp-staging/plugins/woocommerce-extra-checkout-fields-for-brazil.zip --activate 2>/dev/null || true

        enable_gateways "woo-mercado-pago-custom" "woo-mercado-pago-pix" "woo-mercado-pago-ticket" "woo-mercado-pago-basic" "woo-mercado-pago-credits"
        ;;

    # ========================================================================
    # MLA — Argentina
    # ========================================================================
    mla)
        install_locale "es_AR"
        configure_currency "ARS" "left_space" "." "," "2"
        configure_location "AR:C" "Av. Corrientes 1234" "Buenos Aires" "C1043AAZ"
        configure_tax "AR" "21.0000" "IVA"
        configure_shipping "Argentina" "AR" "500.00"

        create_product "Remera Basica" "8500.00"
        create_product "Zapatillas Deportivas" "45000.00"
        create_product "Mochila Urban" "25000.00"
        create_product "Reloj Digital" "65000.00"
        create_product "Auriculares Bluetooth" "15000.00"

        enable_gateways "woo-mercado-pago-custom" "woo-mercado-pago-ticket" "woo-mercado-pago-basic" "woo-mercado-pago-credits"
        ;;

    # ========================================================================
    # MLM — Mexico
    # ========================================================================
    mlm)
        install_locale "es_MX"
        configure_currency "MXN" "left_space" "," "." "2"
        configure_location "MX:DF" "Av. Reforma 500" "Ciudad de Mexico" "06600"
        configure_tax "MX" "16.0000" "IVA"
        configure_shipping "Mexico" "MX" "99.00"

        create_product "Playera Basica" "349.00"
        create_product "Tenis Deportivos" "1799.00"
        create_product "Mochila Urban" "899.00"
        create_product "Reloj Digital" "2499.00"
        create_product "Audifonos Bluetooth" "599.00"

        enable_gateways "woo-mercado-pago-custom" "woo-mercado-pago-ticket" "woo-mercado-pago-basic" "woo-mercado-pago-credits"
        ;;

    # ========================================================================
    # MCO — Colombia
    # ========================================================================
    mco)
        install_locale "es_CO"
        configure_currency "COP" "left_space" "." "," "0"
        configure_location "CO:DC" "Carrera 7 No. 71-21" "Bogota" "110231"
        configure_tax "CO" "19.0000" "IVA"
        configure_shipping "Colombia" "CO" "12000"

        create_product "Camiseta Basica" "85000"
        create_product "Tenis Deportivos" "350000"
        create_product "Morral Urban" "180000"
        create_product "Reloj Digital" "450000"
        create_product "Audifonos Bluetooth" "120000"

        enable_gateways "woo-mercado-pago-custom" "woo-mercado-pago-ticket" "woo-mercado-pago-basic" "woo-mercado-pago-pse"
        ;;

    # ========================================================================
    # MLC — Chile
    # ========================================================================
    mlc)
        install_locale "es_CL"
        configure_currency "CLP" "left_space" "." "," "0"
        configure_location "CL:RM" "Av. Providencia 1234" "Santiago" "7500000"
        configure_tax "CL" "19.0000" "IVA"
        configure_shipping "Chile" "CL" "3000"

        create_product "Polera Basica" "14990"
        create_product "Zapatillas Deportivas" "69990"
        create_product "Mochila Urban" "39990"
        create_product "Reloj Digital" "89990"
        create_product "Audifonos Bluetooth" "24990"

        enable_gateways "woo-mercado-pago-custom" "woo-mercado-pago-basic"
        ;;

    # ========================================================================
    # MLU — Uruguay
    # ========================================================================
    mlu)
        install_locale "es_UY"
        configure_currency "UYU" "left_space" "." "," "2"
        configure_location "UY:MO" "Av. 18 de Julio 1234" "Montevideo" "11100"
        configure_tax "UY" "22.0000" "IVA"
        configure_shipping "Uruguay" "UY" "200.00"

        create_product "Remera Basica" "890.00"
        create_product "Championes Deportivos" "4500.00"
        create_product "Mochila Urban" "2500.00"
        create_product "Reloj Digital" "6500.00"
        create_product "Auriculares Bluetooth" "1500.00"

        enable_gateways "woo-mercado-pago-custom" "woo-mercado-pago-ticket" "woo-mercado-pago-basic"
        ;;

    # ========================================================================
    # MPE — Peru
    # ========================================================================
    mpe)
        install_locale "es_PE"
        configure_currency "PEN" "left_space" "," "." "2"
        configure_location "PE:LIM" "Av. Javier Prado Este 4600" "Lima" "15023"
        configure_tax "PE" "18.0000" "IGV"
        configure_shipping "Peru" "PE" "15.00"

        create_product "Polo Basico" "59.90"
        create_product "Zapatillas Deportivas" "289.90"
        create_product "Mochila Urban" "159.90"
        create_product "Reloj Digital" "399.90"
        create_product "Audifonos Bluetooth" "99.90"

        enable_gateways "woo-mercado-pago-custom" "woo-mercado-pago-ticket" "woo-mercado-pago-basic" "woo-mercado-pago-yape"
        ;;

    *)
        echo "[mp-dev] ERROR: Unknown site '$SITE'. Valid: mlb, mla, mlm, mco, mlc, mlu, mpe"
        exit 1
        ;;
esac

# ============================================================================
# MP credentials and test mode (runs for all countries)
# ============================================================================
echo "[mp-dev] Configuring Mercado Pago credentials..."
configure_mp_credentials

# Set the MP site_id so country-specific checkout forms render correctly
SITE_ID=$(echo "$SITE" | tr '[:lower:]' '[:upper:]')
$WP option update _site_id_v1 "$SITE_ID" 2>/dev/null || true

echo "[mp-dev] Store configured for $SITE."
